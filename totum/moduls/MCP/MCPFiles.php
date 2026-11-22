<?php

namespace totum\moduls\MCP;

use totum\common\errorException;
use totum\common\User;
use totum\config\Conf;
use totum\fieldTypes\File;
use totum\models\TmpTables;

/**
 * Bytes of files for agents. The answer of a tool goes only into the context of the model, so files move outside of it:
 * stdio (the server on the disk of the agent) — files are saved to / read from local paths;
 * HTTP — short-living links with a token for curl (/MCP/file/<token> — download, /MCP/upload/<token> — upload),
 * the password of the agent is in the config of the MCP client, the model does not know it.
 * Small generated files can be passed inline (base64 / text).
 */
class MCPFiles
{
    public const LINK_TTL = 900;
    public const UPLOAD_TTL = 3600;
    public const INLINE_MAX = 1024 * 1024;
    public const IMAGE_MAX = 5 * 1024 * 1024;
    /* the folders of the agents are their work folders: the files live longer than tmp files (2 h) */
    public const AGENTS_DIR_TTL = 86400;
    /* names with "_" are cleaned by clean-schema-tmp-tables after 2 hours */
    protected const LINKS = '_mcp_file_links';
    protected const UPLOADS = '_mcp_uploads';
    protected const FILE_UPLOADS = '_mcp_linktofileupload';

    /**
     * @param string|null $baseUrl https://host of the HTTP server; null — stdio
     */
    public function __construct(protected Conf $Config, protected User $User, protected ?string $baseUrl)
    {
    }

    public function isLocal(): bool
    {
        return $this->baseUrl === null;
    }

    public static function maxSize(): int
    {
        return (int)(Conf::$MaxFileSizeMb * 1024 * 1024);
    }

    public static function size(int|float|null $bytes): string
    {
        $bytes = (int)$bytes;
        if ($bytes < 1024) {
            return $bytes . ' B';
        }
        return $bytes > 102400 ? round($bytes / 1048576, 1) . ' MB' : round($bytes / 1024) . ' KB';
    }

    /* ======================== downloads ======================== */

    /**
     * Default folder of the agent for files on the disk of the stdio server
     */
    public function defaultDir(): string
    {
        return static::agentsDir($this->Config) . $this->Config->getSchema() . '/' . $this->User->getId() . '/';
    }

    /**
     * Folders of the agents (stdio): totumTmpfiles/mcp/<schema>/<user id>/ — next to the tmp dir, so clean-tmp-dir does not clean it with the tmp files
     */
    public static function agentsDir(Conf $Config): string
    {
        return dirname(rtrim($Config->getTmpDir(), '/')) . '/mcp/';
    }

    /**
     * clean-tmp-dir: files of the folders of the agents older than $ttl (by mtime) and empty folders; the root folder stays
     * @return int deleted files
     */
    public static function cleanAgentsDir(Conf $Config, int $ttl = self::AGENTS_DIR_TTL): int
    {
        if (!is_dir($root = static::agentsDir($Config))) {
            return 0;
        }
        $deleted = 0;
        $items = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($items as $item) {
            if ($item->isDir() && !$item->isLink()) {
                @rmdir($item->getPathname());
            } elseif ($item->getMTime() < time() - $ttl && @unlink($item->getPathname())) {
                $deleted++;
            }
        }
        return $deleted;
    }

    /**
     * A file of the server for the agent: stdio — copied to $saveTo (file or folder; default — the folder of the agent), HTTP — a link
     * @param bool $toDir several files: $saveTo is a folder
     * @return string markdown: where it is
     */
    public function deliver(string $path, string $name, ?string $mime, ?string $saveTo, bool $toDir = false): string
    {
        if (!$this->isLocal()) {
            if ($saveTo !== null && $saveTo !== '') {
                throw new errorException('save_to is for the stdio server only (its disk): download the file with the link of the answer');
            }
            return $this->linkLine($path, $name, $mime);
        }
        $target = $saveTo === null || $saveTo === '' ? $this->defaultDir() : $saveTo;
        if (!str_starts_with($target, '/')) {
            throw new errorException('save_to must be an absolute path');
        }
        if ($toDir || str_ends_with($target, '/') || is_dir($target)) {
            $target = rtrim($target, '/') . '/' . static::safeName($name);
        }
        $this->mkdir(dirname($target));
        if (!@copy($path, $target)) {
            throw new errorException('The file is not saved to ' . $target . ': ' . (error_get_last()['message'] ?? 'copy error'));
        }
        return 'saved to `' . $target . '`';
    }

    /**
     * Content of a file made by a code (linkToFileDownload): stdio — a new file in the folder of the agent, HTTP — a link
     */
    public function deliverContent(string $name, string $content, ?string $mime): string
    {
        if (!$this->isLocal()) {
            $path = tempnam($this->Config->getTmpDir(), $this->Config->getSchema() . '.' . $this->User->getId() . '.mcpdownload.');
            file_put_contents($path, $content);
            return $this->linkLine($path, $name, $mime);
        }
        $dir = $this->defaultDir();
        $this->mkdir($dir);
        $name = static::safeName($name);
        $target = $dir . $name;
        for ($i = 1; file_exists($target); $i++) {
            $target = $dir . preg_replace('/(\.[^.]*)?$/', ' (' . $i . ')$1', $name, 1);
        }
        file_put_contents($target, $content);
        return 'saved to `' . $target . '`';
    }

    protected function linkLine(string $path, string $name, ?string $mime): string
    {
        $token = $this->newToken(static::LINKS, ['path' => $path, 'name' => $name, 'mime' => $mime, 'expires' => time() + static::LINK_TTL]);
        return 'download: `curl -sS -o ' . escapeshellarg(static::safeName($name)) . ' ' . escapeshellarg($this->baseUrl . '/MCP/file/' . $token) . '`';
    }

    /**
     * Where the files of the answer are (once before the list of files)
     */
    public function deliverHint(): string
    {
        return $this->isLocal()
            ? "the files are copied on the disk of the server (stdio: it is your disk too) — the paths are below\n"
            : 'the files stay on the server: run the curl commands below in your terminal — every file is saved on your machine where -o points '
            . '(default — the current folder of your shell; change -o to save it elsewhere). The links work ' . (static::LINK_TTL / 60)
            . " min and need no password.\n";
    }

    /**
     * File of a download link (HTTP endpoint /MCP/file/<token>, without authorization: the token is given to the authorized agent)
     * @return array|null {path, name, mime}
     */
    public static function linkData(Conf $Config, string $token): ?array
    {
        $data = static::tokenData($Config, static::LINKS, $token)[1] ?? null;
        return $data && ($data['expires'] ?? 0) >= time() && is_file($data['path'] ?? '') ? $data : null;
    }

    /* ======================== uploads ======================== */

    /**
     * Link for the upload of a file of the disk of the agent (HTTP): curl -T file <url>; the token is the upload id for file values
     */
    public function newUpload(string $name): array
    {
        $name = static::safeName($name);
        $token = $this->newToken(static::UPLOADS, ['name' => $name, 'expires' => time() + static::UPLOAD_TTL]);
        return [$token, $this->baseUrl . '/MCP/upload/' . $token];
    }

    /**
     * HTTP endpoint /MCP/upload/<token>: raw body (curl -T / --data-binary) or multipart field "file" (curl -F file=@…)
     * → tmp file of the user like the upload of web (tmpFileUpload)
     */
    public static function receiveUpload(Conf $Config, string $token): array
    {
        [$userId, $data] = static::tokenData($Config, static::UPLOADS, $token) ?? [null, null];
        if (!$data || ($data['expires'] ?? 0) < time()) {
            throw new errorException('The upload link is not found or expired: get a new one with upload_file', 404);
        }
        if (!empty($data['tmpfile'])) {
            throw new errorException('The file is already uploaded with this link: use its upload id, or get a new link with upload_file', 409);
        }
        $raw = tempnam($Config->getTmpDir(), $Config->getSchema() . '.' . $userId . '.mcpraw.');
        try {
            if (!empty($_FILES['file']['tmp_name']) && is_uploaded_file($_FILES['file']['tmp_name'])) {
                move_uploaded_file($_FILES['file']['tmp_name'], $raw);
            } else {
                $in = fopen('php://input', 'rb');
                $out = fopen($raw, 'wb');
                stream_copy_to_stream($in, $out, static::maxSize() + 1);
                fclose($in);
                fclose($out);
            }
            clearstatcache(true, $raw);
            $size = filesize($raw);
            if (!$size) {
                throw new errorException('The file is empty or not received: send it with curl -T FILE URL', 400);
            }
            if ($size > static::maxSize()) {
                throw new errorException('File > ' . Conf::$MaxFileSizeMb . ' Mb', 413);
            }
            $name = $data['name'];
            $tmpfile = File::createUserTmpFile($Config, $userId, $name, $raw);
        } finally {
            @unlink($raw);
        }
        $data = ['name' => $name, 'tmpfile' => $tmpfile, 'size' => $size, 'expires' => time() + static::UPLOAD_TTL];
        TmpTables::init($Config)->updatePrepared(false, ['tbl' => json_encode($data, JSON_UNESCAPED_UNICODE), 'touched' => date('Y-m-d H:i')],
            ['table_name' => static::UPLOADS, 'user_id' => $userId, 'hash' => $token]);
        return ['upload' => $token, 'name' => $name, 'size' => $size];
    }

    /* ======================== values of file fields ======================== */

    /**
     * A value of a file field from the agent → the value for the core like the form of web: kept files of the cell + new tmp files {name, tmpfile}.
     * Items: {"file"} — keep (rename with "name"), {"path"} (stdio), {"upload"} (upload_file), {"name","base64"} / {"name","text"} (small files).
     * Like the form of web: one file for a not multiple field, accept of the field (the core does not check them)
     * @param array $current files of the cell now
     */
    public function fieldValue(array $field, mixed $value, array $current, string $label): array
    {
        if ($value === null || $value === '' || $value === []) {
            return [];
        }
        if (!is_array($value)) {
            throw new errorException($label . ', ' . $field['name'] . ': the value of a file field is a list of files [{"file": …} to keep, {"path"|"upload"|"base64"|"text": …} new], [] — no files');
        }
        $items = array_is_list($value) ? $value : [$value];
        if (count($items) > 1 && empty($field['multiple'])) {
            throw new errorException($label . ', ' . $field['name'] . ': the field accepts only one file');
        }
        $result = [];
        foreach ($items as $item) {
            if (!is_array($item)) {
                throw new errorException($label . ', ' . $field['name'] . ': every file is an object {"file": …} / {"path": …} / {"upload": …} / {"name": …, "base64"|"text": …}');
            }
            if (key_exists('file', $item)) {
                $kept = array_values(array_filter($current, fn($f) => is_array($f) && ($f['file'] ?? null) === $item['file']))[0]
                    ?? throw new errorException($label . ', ' . $field['name'] . ': the file ' . MCPMarkdown::scalar($item['file'])
                        . ' is not in the field now (keep only its files — "file" from get_value; new files — path / upload / base64 / text)');
                if (is_string($item['name'] ?? null) && trim($item['name']) !== '') {
                    $kept['name'] = static::safeName($item['name']);
                }
                $result[] = $kept;
                continue;
            }
            $result[] = $this->newFile($field, $item, $label . ', ' . $field['name']);
        }
        return $result;
    }

    /**
     * Files for the code of linkToFileUpload: [{name, base64}] like the browser sends them (filesUpload)
     */
    public function uploadFiles(array $items, string $accept): array
    {
        $files = [];
        foreach ($items as $item) {
            if (!is_array($item) || key_exists('file', $item)) {
                throw new errorException('every file is an object {"path": …} / {"upload": …} / {"name": …, "base64"|"text": …}');
            }
            $new = $this->newFile(['name' => 'files', 'accept' => $accept], $item, 'files');
            $path = $this->Config->getTmpDir() . $new['tmpfile'];
            $files[] = ['name' => $new['name'], 'base64' => base64_encode(file_get_contents($path))];
            @unlink($path);
            @unlink($path . '_thumb.jpg');
        }
        return $files;
    }

    /**
     * New file of the agent → tmp file of the user (like the upload of web: image extension by mime, preview of images)
     * @return array {name, tmpfile, size}
     */
    protected function newFile(array $field, array $item, string $label): array
    {
        $sources = array_values(array_intersect(['path', 'upload', 'base64', 'text'], array_keys($item)));
        if (count($sources) !== 1) {
            throw new errorException($label . ': a new file has one source — "path" (stdio), "upload" (id of upload_file), "base64" or "text" (+ "name")');
        }
        $name = is_string($item['name'] ?? null) && trim($item['name']) !== '' ? static::safeName($item['name']) : null;
        $source = null;
        $content = null;
        switch ($sources[0]) {
            case 'path':
                if (!$this->isLocal()) {
                    throw new errorException($label . ': "path" is for the stdio server only (its disk): upload the file with upload_file and pass {"upload": "ID of the upload"}');
                }
                $source = (string)$item['path'];
                if (!str_starts_with($source, '/') || !is_file($source) || !is_readable($source)) {
                    throw new errorException($label . ': the file ' . $source . ' is not found or not readable (an absolute path on the disk of the server)');
                }
                $name ??= static::safeName(basename($source));
                $size = filesize($source);
                break;
            case 'upload':
                $upload = static::tokenData($this->Config, static::UPLOADS, (string)$item['upload']);
                $data = $upload && (int)$upload[0] === (int)$this->User->getId() ? $upload[1] : null;
                if (empty($data['tmpfile']) || ($data['expires'] ?? 0) < time() || !is_file($source = $this->Config->getTmpDir() . $data['tmpfile'])) {
                    throw new errorException($label . ': the upload ' . MCPMarkdown::scalar($item['upload'])
                        . ' is not found, not uploaded yet or expired: get a link with upload_file and upload the file with curl first');
                }
                $name ??= $data['name'];
                $size = filesize($source);
                break;
            default:
                if (!$name) {
                    throw new errorException($label . ': "name" (with extension) is required for ' . $sources[0]);
                }
                $content = $sources[0] === 'base64' ? base64_decode((string)$item['base64'], true) : $item['text'];
                if (!is_string($content)) {
                    throw new errorException($label . ': ' . ($sources[0] === 'base64' ? '"base64" is not valid base64' : '"text" must be a string'));
                }
                $size = strlen($content);
                if ($size > static::INLINE_MAX) {
                    throw new errorException($label . ': inline files are up to ' . static::size(static::INLINE_MAX) . ' — '
                        . ($this->isLocal() ? 'save the file to the disk and pass "path"' : 'upload it with upload_file'));
                }
        }
        if ($size > static::maxSize()) {
            throw new errorException($label . ': File > ' . Conf::$MaxFileSizeMb . ' Mb');
        }
        $original = $name;
        $tmpfile = File::createUserTmpFile($this->Config, $this->User->getId(), $name, $source, $content);
        $path = $this->Config->getTmpDir() . $tmpfile;
        $mime = trim((string)shell_exec('file -b --mime-type ' . escapeshellarg($path)));
        if (is_string($field['accept'] ?? null) && trim($field['accept']) !== '' && !static::accepts($field['accept'], $original, $mime)) {
            @unlink($path);
            @unlink($path . '_thumb.jpg');
            throw new errorException($label . ': only these types are accepted: ' . $field['accept'] . ' (file ' . $original . ', ' . $mime . ')');
        }
        return ['name' => $name, 'tmpfile' => $tmpfile, 'size' => filesize($path)];
    }

    /**
     * accept like the web form (input accept / its check): ".ext", "mime/type", "type/*"; "*" — any
     */
    public static function accepts(string $accept, string $name, string $mime): bool
    {
        $ext = preg_match('/(\.[a-zA-Z0-9]+)$/', $name, $m) ? strtolower($m[1]) : null;
        foreach (explode(',', $accept) as $type) {
            $type = trim($type);
            if ($type === '*' || $type === '*/*' || ($ext && $ext === strtolower($type)) || $type === $mime) {
                return true;
            }
            $parts = explode('/', $type);
            if (($parts[1] ?? null) === '*' && $parts[0] === explode('/', $mime)[0]) {
                return true;
            }
        }
        return false;
    }

    /* ======================== linkToFileUpload ======================== */

    /**
     * limit / type of the window are not saved by the core (only the browser gets them) — kept for submit_file_upload
     */
    public function rememberFileUpload(string $hash, array $data): void
    {
        TmpTables::init($this->Config)->saveByHash(static::FILE_UPLOADS, $this->User, $hash, $data, true);
    }

    public function fileUploadData(string $hash): ?array
    {
        try {
            return TmpTables::init($this->Config)->getByHash(static::FILE_UPLOADS, $this->User, $hash);
        } catch (errorException) {
            return null;
        }
    }

    public function forgetFileUpload(string $hash): void
    {
        TmpTables::init($this->Config)->deleteByHash(static::FILE_UPLOADS, $this->User, $hash);
    }

    /* ======================== helpers ======================== */

    protected function newToken(string $table, array $data): string
    {
        $token = bin2hex(random_bytes(16));
        TmpTables::init($this->Config)->saveByHash($table, $this->User, $token, $data, true);
        return $token;
    }

    /**
     * @return array|null [user_id, data]
     */
    protected static function tokenData(Conf $Config, string $table, string $token): ?array
    {
        if (!preg_match('/^[a-f0-9]{32}$/', $token)) {
            return null;
        }
        $row = TmpTables::init($Config)->getPrepared(['table_name' => $table, 'hash' => $token], 'user_id, tbl');
        return $row ? [(int)$row['user_id'], json_decode($row['tbl'], true)] : null;
    }

    public static function safeName(string $name): string
    {
        $name = trim(str_replace(['/', '\\', "\0"], '_', $name));
        return $name === '' || $name === '.' || $name === '..' ? 'file' : $name;
    }

    protected function mkdir(string $dir): void
    {
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new errorException('The folder ' . $dir . ' is not created');
        }
    }
}
