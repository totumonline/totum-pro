<?php

namespace totum\moduls\MCP;

use totum\common\criticalErrorException;
use totum\common\errorException;
use totum\common\tableSaveOrDeadLockException;
use totum\common\User;
use totum\config\Conf;
use totum\tableTypes\tmpTable;

/**
 * Transport-independent MCP (JSON-RPC 2.0) dispatcher. Used by HTTP (MCPController) and stdio (MCPServe).
 */
class MCPServer
{
    public const SERVER_NAME = 'totum-mcp';
    public const SERVER_VERSION = '1.0.0';
    public const PROTOCOL_VERSIONS = ['2025-06-18', '2025-03-26', '2024-11-05'];

    protected const INSTRUCTIONS = <<<TXT
Totum tables interface for agents. Works like the Totum web table interface with the rights of the agent user.
Start with list_tables, then get_table_schema to learn fields (use field `name` in requests), then read_table.
Calcs tables (type calcs) need `cycle` (cycle id = row id of its cycles table); their answers list the tables and tab_ buttons of the cycle
(section "## Cycle"). A cycle is added with add_row of the cycles table (no insert row) - the answer opens its first table.
A cycles table with one cycle per user is opened by read_table into the cycle of the user (created if needed) - work in the tables of the cycle.
Tmp tables return sess_hash in the header - pass it back.
Filters are stateless: pass `filters` in every call. Cell marks: `Title [key]` select/tree title and key to write,
`✋(x)` manual value differs from calculated x, `✊` manual value equal to calculated, `!ERR` calculation error,
`#block` blocked by format, `#icon-name` icon, `…` cut text (full via get_cell/get_value/get_row), `⏎` new line.
Visual formats (color, bold, background…) are not shown by default: `styles: true` of read_table / get_row gives `#style(color: ##tomato; bold: true)`
(##name — user colors of ttm__colors, the legend is in "## Styles"); get_cell shows them always.
get_cell = right-click panel of a cell: full value, calculated value, style, description, panel buttons, log of manual changes.
Actions (buttons, edits with code) report what they did in "## Side effects". Windows opened by an action are shown at once
in "## Opened window" sections of the same answer: a table (linkToTable / linkToDataTable) is already read like read_table
and has the args to continue (`table`, `sess_hash`, `cycle`, crypted `filters`) - pass exactly these args in the next calls to work in the window;
text/html/json windows show their content; a json window with a button is pressed with submit_json_window, a buttons window - submit_buttons.
`refresh` of a window - re-read the source table when the work in the window is done.
Rows checked with checkmarks in web are `checked_ids` of click_button (buttons marked checked-rows in get_table_schema need them).
Tables hidden from the tree are not in list_tables: they are opened only by buttons/links of other tables - do not open them directly.
Files do not go through the answers: get_file saves a file to the disk of the stdio server or gives a curl link (HTTP);
new files are {"path"} (stdio) / {"upload"} of upload_file (HTTP) / small {"name","text"|"base64"}; a file upload window - submit_file_upload.
Tasks may come as notifications: get_notifications shows them like the popups of web, notification_update closes / postpones a handled one.
Comments "(N, unread M)" are not read by you: get_value / get_cell show them (#unread) and mark them as read, mark_comments_read marks without opening.
Documentation of Totum (interface, fields, tables, codes and functions): search_docs finds sections by words, read_docs reads them (no internet needed).
TXT;

    protected MCPTools $Tools;

    /**
     * @param Conf $Config
     * @param \Closure $userProvider fn(Conf $Config): User — authorization of every tools/call
     * @param bool $freshConfigPerCall for long-living process (stdio): clean config caches before every tools/call
     * @param string|null $baseUrl https://host of the HTTP server (links of files); null — stdio (files on the disk of the server)
     * @param bool $creator creator view (--creator=true / header TTM-Creator): tools and instructions of the admin layer, the user must be a creator
     */
    public function __construct(protected Conf $Config, protected \Closure $userProvider, protected bool $freshConfigPerCall = false,
                                ?string $baseUrl = null, protected bool $creator = false)
    {
        $this->Tools = new MCPTools($baseUrl, $creator);
    }

    /**
     * @return string|null JSON answer or null if nothing to answer (notifications only)
     */
    public function handlePayload(string $payload): ?string
    {
        $data = json_decode($payload, true);
        if (!is_array($data)) {
            return $this->encode($this->error(null, -32700, 'Parse error'));
        }
        if (array_is_list($data) && $data) {
            $answers = array_values(array_filter(array_map(fn($m) => is_array($m) ? $this->handle($m) : $this->error(null, -32600, 'Invalid Request'), $data)));
            return $answers ? $this->encode($answers) : null;
        }
        $answer = $this->handle($data);
        return $answer ? $this->encode($answer) : null;
    }

    public function handle(array $message): ?array
    {
        $id = $message['id'] ?? null;
        $isNotification = !key_exists('id', $message);
        $method = $message['method'] ?? null;

        if (($message['jsonrpc'] ?? null) !== '2.0' || !is_string($method)) {
            return $isNotification ? null : $this->error($id, -32600, 'Invalid Request');
        }
        $params = is_array($message['params'] ?? null) ? $message['params'] : [];

        $result = match ($method) {
            'initialize' => $this->initialize($params),
            'ping' => [],
            'tools/list' => ['tools' => $this->Tools->definitions()],
            'tools/call' => $this->callTool($params),
            default => null
        };

        if ($isNotification) {
            return null;
        }
        if (is_null($result)) {
            return $this->error($id, -32601, 'Method not found: ' . $method);
        }
        return ['jsonrpc' => '2.0', 'id' => $id, 'result' => $result ?: new \stdClass()];
    }

    protected function initialize(array $params): array
    {
        $version = in_array($params['protocolVersion'] ?? null, static::PROTOCOL_VERSIONS)
            ? $params['protocolVersion']
            : static::PROTOCOL_VERSIONS[0];
        return [
            'protocolVersion' => $version,
            'capabilities' => ['tools' => ['listChanged' => false]],
            'serverInfo' => ['name' => static::SERVER_NAME, 'version' => static::SERVER_VERSION],
            'instructions' => static::INSTRUCTIONS . ($this->creator ? "\n" . MCPCreator::INSTRUCTIONS : ''),
        ];
    }

    protected function callTool(array $params): array
    {
        $name = $params['name'] ?? '';
        $args = is_array($params['arguments'] ?? null) ? $params['arguments'] : [];
        if ($this->freshConfigPerCall) {
            $this->freshConfig();
        }

        $tries = 0;
        $isError = true;
        $content = [];
        ob_start();
        try {
            while (true) {
                try {
                    $User = ($this->userProvider)($this->Config);
                    if ($this->creator) {
                        MCPAuth::checkCreator($User);
                    }
                    $this->setCryptKey($User);
                    $text = $this->Tools->call($this->Config, $User, $name, $args);
                    $content = $this->Tools->extraContent();
                    $isError = $this->Tools->isFailed();
                    break;
                } catch (tableSaveOrDeadLockException $e) {
                    $this->resetConfig();
                    if (++$tries >= 5) {
                        throw new errorException($this->Config->getLangObj()->translate('Conflicts of access to the table error'));
                    }
                }
            }
        } catch (errorException|criticalErrorException $e) {
            $text = 'ERROR: ' . MCPMarkdown::plain($e->getMessage());
        } catch (\Throwable $e) {
            $this->Config->getLogger('sql')->error('MCP: ' . $e->getMessage() . ' ' . $e->getFile() . ':' . $e->getLine());
            $text = 'ERROR: ' . MCPMarkdown::plain($e->getMessage());
        } finally {
            if ($isError) {
                $this->resetConfig();
            } elseif ($this->Tools->isRolledBack()) {
                $this->freshConfig();
            }
            $garbage = ob_get_clean();
            if ($garbage !== '' && $garbage !== false) {
                defined('STDERR') ? fwrite(STDERR, $garbage . PHP_EOL) : error_log('MCP output: ' . $garbage);
            }
        }

        return ['content' => [['type' => 'text', 'text' => $text], ...$content], 'isError' => $isError];
    }

    /**
     * Strings crypted with the "session" key (filters of links of opened windows) must work in the next calls,
     * but the HTTP agent has no session: a stable key of the agent user from the server secret instead of the random key of a web session
     */
    protected function setCryptKey(User $User): void
    {
        try {
            $secret = $this->Config->getCryptKeyFileContent();
        } catch (\Throwable) {
            $secret = (string)$this->Config->getCryptSolt();
        }
        $_SESSION['crypt_key'] = hash_hmac('md5', 'mcp/' . $this->Config->getSchema() . '/' . $User->getId(), $secret);
    }

    /**
     * Rollback terminates the db connection, so the next calls work with a clean config
     */
    protected function resetConfig(): void
    {
        $this->Config->getSql()->transactionRollBack();
        $this->freshConfig();
    }

    /**
     * tmp tables are cached statically with Totum/Sql of the call that loaded them:
     * without the reset a later call writes through the old (committed) connection and its changes are not notified
     */
    protected function freshConfig(): void
    {
        $this->Config = $this->Config->getClearConf();
        tmpTable::clearInstances();
    }

    protected function error($id, int $code, string $message): array
    {
        return ['jsonrpc' => '2.0', 'id' => $id, 'error' => ['code' => $code, 'message' => $message]];
    }

    protected function encode(array $data): string
    {
        return json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    }
}
