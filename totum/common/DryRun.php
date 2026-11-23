<?php

namespace totum\common;

/**
 * Dry run of codes (Totum::setDryRun): the external actions that a rollback of the transaction does not undo
 * (mail, http, ssh, separate processes, external databases, services…) are blocked by kinds.
 * A blocked action: stop — the execution stops at it (DryRunStopException), skip — it is not done (the function returns null).
 * Mocks: the action is not done, the function returns the mock result and the run goes on —
 * rules of the call (any action, also not blocked) and «mock» lines in <agent> of the help of the field of the code (blocked actions only).
 * Every external action met (done or not) is in the journal.
 */
class DryRun
{
    public const KINDS = [
        'email' => 'emailSend, notificationSend (eml)',
        'http' => 'getFromScript, getFromSoap',
        'ssh' => 'execSSH',
        'exec' => 'exec(ssh: true), notificationSend (custom codes in a separate process) — bin/totum exec commits in its own transaction',
        'db' => 'proDbExecQuery (external databases)',
        'imap' => 'proImapGetEmail (marks the email as read)',
        'services' => 'serviceXlsxParser, serviceXlsxGenerator, serviceDocxGenerator, servicePDFGenerator, serviceAskOpenai, serviceAskOpenaiList',
        'schema' => 'schemaGzStringUpload, schemaUpdate',
        'gom' => 'checkGomDaemonds',
    ];
    /* functions of codes with external actions → kind (exec only with ssh: — see externalCalls) */
    public const FUNCTIONS = [
        'emailSend' => 'email', 'notificationSend' => 'email', 'getFromScript' => 'http', 'getFromSoap' => 'http', 'execSSH' => 'ssh', 'exec' => 'exec',
        'proDbExecQuery' => 'db', 'proImapGetEmail' => 'imap', 'serviceXlsxParser' => 'services', 'serviceXlsxGenerator' => 'services',
        'serviceDocxGenerator' => 'services', 'servicePDFGenerator' => 'services', 'serviceAskOpenai' => 'services', 'serviceAskOpenaiList' => 'services',
        'schemaGzStringUpload' => 'schema', 'schemaUpdate' => 'schema', 'checkGomDaemonds' => 'gom',
    ];
    public const MOCK_KEYS = ['function', 'kind', 'table', 'field', 'id', 'match', 'result'];

    protected array $blocked;
    protected array $mocks = [];
    protected array $journal = [];
    protected ?array $stop = null;

    /**
     * @param array|string $block 'all' or kinds of KINDS
     * @param array $allow kinds that are done anyway (for block 'all')
     * @param bool $stopOnBlocked true — stop at a blocked action, false — skip it
     * @param array $mocks rules [{function|kind, table?, field?, id?, match?, result}] — the first matching one is used
     * @param bool $helpMocks use «mock» lines of <agent> of the help of the field of the code for blocked actions
     */
    public function __construct(array|string $block = 'all', array $allow = [], protected bool $stopOnBlocked = true, array $mocks = [],
                                protected bool $helpMocks = true)
    {
        $kinds = $block === 'all' ? array_keys(static::KINDS) : $block;
        foreach (['block' => $kinds, 'allow' => $allow] as $name => $list) {
            if ($unknown = array_diff($list, array_keys(static::KINDS))) {
                throw new errorException('Unknown kinds of external actions in ' . $name . ': ' . implode(', ', $unknown)
                    . '. Kinds: ' . implode(', ', array_keys(static::KINDS)));
            }
        }
        $this->blocked = array_values(array_diff($kinds, $allow));
        foreach ($mocks as $i => $mock) {
            if (!is_array($mock) || !key_exists('result', $mock) || (empty($mock['function']) && empty($mock['kind']))) {
                throw new errorException('Mock #' . ($i + 1) . ' must be {"function" or "kind", "table"?, "field"?, "id"?, "match"?, "result"}');
            }
            if ($unknown = array_diff(array_keys($mock), static::MOCK_KEYS)) {
                throw new errorException('Mock #' . ($i + 1) . ': unknown keys ' . implode(', ', $unknown) . '. Keys: ' . implode(', ', static::MOCK_KEYS));
            }
            if (!empty($mock['kind']) && !key_exists($mock['kind'], static::KINDS)) {
                throw new errorException('Mock #' . ($i + 1) . ': unknown kind ' . $mock['kind'] . '. Kinds: ' . implode(', ', array_keys(static::KINDS)));
            }
            $this->mocks[] = $mock + ['source' => 'mock #' . ($i + 1)];
        }
    }

    /**
     * @param array $details what the action would do (to, uri, command…)
     * @param array $where table / cycle / field / id of the code
     * @param string|null $help help of the field of the code: «mock» lines of its <agent> parts
     * @param mixed $result the mock result when the action is not done
     * @return bool true — do the action, false — not done: the function returns $result (mock or null)
     * @throws DryRunStopException
     */
    public function check(string $kind, string $function, array $details, array $where = [], ?string $help = null, mixed &$result = null): bool
    {
        $result = null;
        $entry = ['kind' => $kind, 'function' => $function, 'details' => $details, 'where' => $where];
        /* after a stop swallowed by a code everything is blocked: the run ends with a rollback */
        if ($this->stop) {
            $this->journal[] = $entry + ['status' => 'after stop'];
            throw new DryRunStopException($this->stop);
        }
        $blocked = in_array($kind, $this->blocked, true);
        if (($mock = static::findMock($this->mocks, $entry))
            || ($blocked && $this->helpMocks && $help && ($mock = static::findMock(static::mocksFromHelp($help, $where['field'] ?? ''), $entry)))) {
            $result = $mock['result'];
            $this->journal[] = $entry + ['status' => 'mocked', 'mock' => $mock['source'], 'result' => $result];
            return false;
        }
        if (!$blocked) {
            $this->journal[] = $entry + ['status' => 'done'];
            return true;
        }
        if ($this->stopOnBlocked) {
            $this->journal[] = $this->stop = $entry + ['status' => 'stopped'];
            throw new DryRunStopException($this->stop);
        }
        $this->journal[] = $entry + ['status' => 'skipped'];
        return false;
    }

    protected static function findMock(array $mocks, array $entry): ?array
    {
        $details = null;
        foreach ($mocks as $mock) {
            if ((!empty($mock['function']) && strcasecmp($mock['function'], $entry['function']) !== 0)
                || (!empty($mock['kind']) && $mock['kind'] !== $entry['kind'])) {
                continue;
            }
            foreach (['table', 'field', 'id'] as $key) {
                if (isset($mock[$key]) && (string)$mock[$key] !== (string)($entry['where'][$key] ?? '')) {
                    continue 2;
                }
            }
            if (isset($mock['match']) && $mock['match'] !== '') {
                $details ??= mb_strtolower(json_encode($entry['details'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
                if (!str_contains($details, mb_strtolower((string)$mock['match']))) {
                    continue;
                }
            }
            return $mock;
        }
        return null;
    }

    /**
     * Mocks of a field from the <agent> parts of its help (hidden in web, shown to agents), one per line:
     *   mock <function or kind>[ "<text in the details>"]: <result — JSON (may go on the next lines) or text>
     * e.g. mock getFromScript "api.cbr.ru": {"rate": 92.5} · mock emailSend: true · mock http: "ok"
     */
    public static function mocksFromHelp(?string $help, string $field = ''): array
    {
        static $cache = [];
        $help = (string)$help;
        if (!str_contains($help, '<agent>')) {
            return [];
        }
        $key = md5($help) . '/' . $field;
        if (key_exists($key, $cache)) {
            return $cache[$key];
        }
        preg_match_all('`<agent>(.*?)</agent>`su', $help, $parts);
        $text = preg_replace('`<br\s*/?>|</(p|div|li|h\d)>`i', "\n", implode("\n", $parts[1]));
        $text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        $mocks = [];
        $open = null;
        $close = function () use (&$open, &$mocks, $field) {
            if ($open) {
                $raw = trim($open['raw']);
                $value = json_decode($raw, true);
                $mock = [key_exists($open['name'], static::KINDS) ? 'kind' : 'function' => $open['name'],
                    'result' => $value === null && $raw !== 'null' ? $raw : $value,
                    'source' => 'help of the field' . ($field !== '' ? ' ' . $field : '')];
                if ($open['match'] !== '') {
                    $mock['match'] = $open['match'];
                }
                $mocks[] = $mock;
                $open = null;
            }
        };
        foreach (preg_split('/\R/u', $text) as $line) {
            if (preg_match('/^\s*mock\s+([A-Za-z_][A-Za-z0-9_]*)(?:\s+"([^"]*)")?\s*:\s*(.*)$/u', $line, $m)) {
                $close();
                $open = ['name' => $m[1], 'match' => $m[2], 'raw' => $m[3]];
            } elseif ($open) {
                $open['raw'] .= "\n" . $line;
            } else {
                continue;
            }
            /* a JSON object / list goes on until it is valid; any other value is one line */
            $raw = ltrim($open['raw']);
            if (!in_array($raw[0] ?? '', ['{', '['], true) || json_decode($raw) !== null) {
                $close();
            }
        }
        $close();
        return $cache[$key] = $mocks;
    }

    /**
     * Functions with external actions in a code (names of functions are case-insensitive; exec — only with ssh:)
     * @return array [function => kind]
     */
    public static function externalCalls(string $code): array
    {
        $calls = [];
        if (preg_match_all('/\b(' . implode('|', array_keys(static::FUNCTIONS)) . ')\s*\(([^)]*)/i', $code, $matches, PREG_SET_ORDER)) {
            $names = array_combine(array_map('strtolower', array_keys(static::FUNCTIONS)), array_keys(static::FUNCTIONS));
            foreach ($matches as [, $name, $params]) {
                $name = $names[strtolower($name)];
                if ($name !== 'exec' || preg_match('/\bssh\s*:/', $params)) {
                    $calls[$name] = static::FUNCTIONS[$name];
                }
            }
        }
        return $calls;
    }

    /**
     * Has a mock of the help for the function (by its name or kind)
     */
    public static function hasHelpMock(?string $help, string $function): bool
    {
        foreach (static::mocksFromHelp($help) as $mock) {
            if (strcasecmp($mock['function'] ?? '', $function) === 0 || ($mock['kind'] ?? '') === (static::FUNCTIONS[$function] ?? null)) {
                return true;
            }
        }
        return false;
    }

    public function getBlocked(): array
    {
        return $this->blocked;
    }

    public function isStopOnBlocked(): bool
    {
        return $this->stopOnBlocked;
    }

    public function hasMocks(): bool
    {
        return (bool)$this->mocks;
    }

    public function isHelpMocks(): bool
    {
        return $this->helpMocks;
    }

    public function getJournal(): array
    {
        return $this->journal;
    }

    public function getStop(): ?array
    {
        return $this->stop;
    }
}
