<?php

namespace totum\moduls\MCP;

use Psr\Http\Message\ServerRequestInterface;
use totum\common\Auth;
use totum\common\controllers\Controller;
use totum\common\errorException;
use totum\config\Conf;

/**
 * MCP over HTTP (Streamable HTTP, stateless mode): every JSON-RPC message is a separate POST to /MCP/,
 * answer is application/json, authorization "Authorization: Basic" in every request.
 */
class MCPController extends Controller
{
    public function doIt(ServerRequestInterface $request, bool $output)
    {
        if (!isset($_SESSION)) {
            $_SESSION = [];
        }
        $method = strtoupper($request->getMethod());
        if (preg_match('`^/MCP/(file|upload)/([^/?]*)`', $request->getUri()->getPath(), $match)) {
            $match[1] === 'file' ? $this->download($match[2]) : $this->upload($match[2], $method);
        }
        if ($method === 'OPTIONS') {
            $this->answer(204, null, ['Allow' => 'POST, OPTIONS']);
        }
        if ($method !== 'POST') {
            $this->answer(405, $this->jsonError(-32000, 'Method not allowed. Use POST with JSON-RPC body.'), ['Allow' => 'POST, OPTIONS']);
        }

        try {
            $User = MCPAuth::byRequest($this->Config, $request);
        } catch (errorException $e) {
            $this->answer(401, $this->jsonError(-32001, $e->getMessage()), ['WWW-Authenticate' => 'Basic realm="Totum MCP", charset="UTF-8"']);
        }

        $creator = $request->hasHeader(MCPAuth::CREATOR_HEADER) && MCPAuth::isFlagOn($request->getHeaderLine(MCPAuth::CREATOR_HEADER));
        if ($creator) {
            try {
                MCPAuth::checkCreator($User);
            } catch (errorException $e) {
                $this->answer(403, $this->jsonError(-32001, $e->getMessage() . ' (header ' . MCPAuth::CREATOR_HEADER . ')'));
            }
        }

        $InitConfig = $this->Config;
        $Server = new MCPServer($this->Config,
            fn(Conf $Config) => $Config === $InitConfig ? $User : (Auth::getUserById($Config, $User->getId()) ?? throw new errorException('User is not found')),
            false, $this->baseUrl($request), $creator);

        ob_start();
        $answer = $Server->handlePayload((string)$request->getBody());
        ob_end_clean();

        if (is_null($answer)) {
            $this->answer(202, null);
        }
        $this->answer(200, $answer);
    }

    /**
     * Links of files (get_file, linkToFileDownload): the token is given to the authorized agent, curl downloads without the password
     */
    protected function download(string $token): never
    {
        if (!($file = MCPFiles::linkData($this->Config, $token))) {
            $this->answer(404, json_encode(['error' => 'The link is not found or expired: get a new one'], JSON_UNESCAPED_UNICODE));
        }
        http_response_code(200);
        header('Content-Type: ' . ($file['mime'] ?: 'application/octet-stream'));
        header('Content-Length: ' . filesize($file['path']));
        header("Content-Disposition: attachment; filename*=UTF-8''" . rawurlencode($file['name']));
        readfile($file['path']);
        die;
    }

    /**
     * Upload of a file of the agent by the link of upload_file (PUT/POST: raw body or multipart "file")
     */
    protected function upload(string $token, string $method): never
    {
        if (!in_array($method, ['PUT', 'POST'])) {
            $this->answer(405, json_encode(['error' => 'Use PUT (curl -T <file> <url>) or POST'], JSON_UNESCAPED_UNICODE), ['Allow' => 'PUT, POST']);
        }
        try {
            $this->answer(200, json_encode(['ok' => true] + MCPFiles::receiveUpload($this->Config, $token)
                    + ['next' => 'pass {"upload": "' . $token . '"} as a file of a file field or of submit_file_upload'], JSON_UNESCAPED_UNICODE));
        } catch (\Throwable $e) {
            $this->answer(in_array($e->getCode(), [400, 404, 409, 413], true) ? $e->getCode() : 400, json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE));
        }
    }

    protected function baseUrl(ServerRequestInterface $request): string
    {
        $uri = $request->getUri();
        return ($uri->getScheme() ?: 'https') . '://' . $uri->getHost() . ($uri->getPort() ? ':' . $uri->getPort() : '');
    }

    protected function jsonError(int $code, string $message): string
    {
        return json_encode(['jsonrpc' => '2.0', 'id' => null, 'error' => ['code' => $code, 'message' => $message]], JSON_UNESCAPED_UNICODE);
    }

    protected function answer(int $status, ?string $body, array $headers = []): never
    {
        http_response_code($status);
        foreach ($headers as $name => $value) {
            header($name . ': ' . $value);
        }
        if (!is_null($body)) {
            header('Content-Type: application/json; charset=utf-8');
            echo $body;
        }
        die;
    }
}
