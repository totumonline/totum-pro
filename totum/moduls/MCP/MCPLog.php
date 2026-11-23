<?php

namespace totum\moduls\MCP;

use totum\common\DryRun;
use totum\common\errorException;
use totum\common\Totum;

/**
 * Debug of a call (parameter debug, creator view): the calculation log of Totum (CalculateLog, like the button "Debugging" of web)
 * and the dry run (DryRun: external actions that a rollback does not undo are blocked); the transaction is rolled back by default
 */
class MCPLog
{
    public const LOG_TYPES = ['c', 'a', 'C', 's', 'f', 'flds', 'all'];
    public const DEFAULT_LOG = ['c', 'a'];
    public const DEFAULT_LIMIT = 300;
    public const MAX_LIMIT = 2000;
    protected const TEXT_LENGTH = 300;
    protected const DETAIL_LENGTH = 500;

    public const PROP = ['type' => ['object', 'boolean'], 'description' => 'Creator view: run with the calculation log (like "Debugging" of web) '
        . 'in a transaction that is ROLLED BACK by default; true — the defaults. {"log": ["c","a"] (c — codes, a — action codes, C — why the table is changed, '
        . 's — selects, f — formats, flds — calculation time of fields, all; [] — no log), "commit": false (true — save, if no blocked external action is met), '
        . '"block": "all" | ["email","http","ssh","exec","db","imap","services","schema","gom"] — external actions that a rollback does not undo are not done (default all), '
        . '"allow": [kinds done anyway], "on_block": "stop" (default: stop at the first blocked action and show it — ask the user, then repeat with allow) '
        . '| "skip" (not done: the function returns null, the run goes on; not with commit), '
        . '"mocks": [{"function": "getFromScript" or "kind": "http", "table"?, "field"?, "id"?, "match"?: "text in the details (uri…)", "result": <what the function returns>}] '
        . 'or {"<function or kind>": <result>} — the action is NOT done, the function returns the result, the run goes on (any action, also not blocked; not with commit), '
        . '"help_mocks": true (blocked actions take the «mock» lines of <agent> of the help of the field of the code; off with commit), "log_limit": 300 (lines of the log, max 2000)}'];

    public array $log;
    public bool $commit;
    public int $limit;
    public DryRun $DryRun;

    public function __construct(mixed $debug)
    {
        $debug = $debug === true ? [] : $debug;
        if (!is_array($debug) || ($debug && array_is_list($debug))) {
            throw new errorException('debug must be true or an object {"log", "commit", "block", "allow", "on_block", "log_limit"}');
        }
        $log = $debug['log'] ?? static::DEFAULT_LOG;
        $this->log = $log === false ? [] : array_values((array)$log);
        if ($unknown = array_diff($this->log, static::LOG_TYPES)) {
            throw new errorException('Unknown log types: ' . implode(', ', $unknown) . '. Types: ' . implode(', ', static::LOG_TYPES));
        }
        $this->commit = !empty($debug['commit']);
        $onBlock = $debug['on_block'] ?? 'stop';
        if (!in_array($onBlock, ['stop', 'skip'], true)) {
            throw new errorException('debug.on_block must be "stop" or "skip"');
        }
        if ($this->commit && $onBlock === 'skip') {
            throw new errorException('debug.on_block "skip" with commit: true is not allowed — the data would be saved with the results of not done actions (null)');
        }
        $mocks = $debug['mocks'] ?? [];
        if (!is_array($mocks)) {
            throw new errorException('debug.mocks must be a list of {"function" or "kind", "table"?, "field"?, "id"?, "match"?, "result"} or an object {"<function or kind>": <result>}');
        }
        if ($mocks && !array_is_list($mocks)) {
            $mocks = array_map(fn($name, $result) => [key_exists($name, DryRun::KINDS) ? 'kind' : 'function' => $name, 'result' => $result],
                array_keys($mocks), $mocks);
        }
        if ($this->commit && $mocks) {
            throw new errorException('debug.mocks with commit: true is not allowed — the data would be saved with the results of not done actions (mocks)');
        }
        $this->limit = min(max((int)($debug['log_limit'] ?? static::DEFAULT_LIMIT), 10), static::MAX_LIMIT);
        $this->DryRun = new DryRun($debug['block'] ?? 'all', (array)($debug['allow'] ?? []), $onBlock === 'stop', $mocks,
            !$this->commit && ($debug['help_mocks'] ?? true) !== false);
    }

    public function apply(Totum $Totum): void
    {
        $Totum->setCalcsTypesLog($this->log);
        $Totum->setDryRun($this->DryRun);
    }

    /**
     * First line of the answer: what happened with the transaction
     */
    public function status(bool $saved, ?string $error): string
    {
        return match (true) {
            $error !== null => 'ERROR: ' . $error . "\n**DEBUG: rolled back — nothing is saved**\n",
            (bool)$this->DryRun->getStop() => "**DEBUG: stopped at a blocked external action — rolled back, nothing is saved**\n",
            $saved => "**DEBUG: saved (debug.commit: true)**\n",
            default => "**DEBUG: rolled back — nothing is saved** (debug.commit: true saves)\n",
        };
    }

    /**
     * Sections after the answer of the tool: settings of the run, the stop, the journal of external actions, errors and the log
     */
    public function sections(Totum $Totum, ?string $errorPath = null): string
    {
        $blocked = $this->DryRun->getBlocked();
        $out = "\n## Debug\n" . 'external actions blocked: ' . ($blocked ? implode(', ', $blocked) : 'none')
            . ' · on block: ' . ($this->DryRun->isStopOnBlocked() ? 'stop' : 'skip (the function returns null)')
            . ' · mocks: ' . (($mocks = array_filter([$this->DryRun->hasMocks() ? 'of the call' : null,
                $this->DryRun->isHelpMocks() ? 'of the helps of fields (<agent>)' : null])) ? implode(' + ', $mocks) : 'off')
            . ' · log: ' . ($this->log ? implode(', ', $this->log) : 'off') . "\n";
        if ($errorPath) {
            $out .= 'error path: ' . MCPMarkdown::plain($errorPath) . "\n";
        }
        if ($stop = $this->DryRun->getStop()) {
            $out .= $this->stopSection($stop);
        }
        if ($journal = $this->DryRun->getJournal()) {
            $rows = [];
            foreach ($journal as $i => $entry) {
                $rows[] = [$i + 1, $entry['status'] . ($entry['status'] === 'mocked' ? ' (' . $entry['mock'] . ')' : ''), $entry['kind'], $entry['function'],
                    MCPMarkdown::esc(static::where($entry['where'])), MCPMarkdown::esc(MCPMarkdown::cut(static::details($entry['details'], ', '), 300)),
                    $entry['status'] === 'mocked' ? MCPMarkdown::esc(MCPMarkdown::cut(MCPMarkdown::scalar($entry['result']), 200)) : ''];
            }
            $out .= "\n### External actions\n" . MCPMarkdown::mdTable(['#', 'status', 'kind', 'function', 'where', 'details', 'returned'], $rows)
                . 'done — done for real (not blocked); mocked — not done, the function returned the mock; skipped — not done, the function returned null; '
                . 'stopped — the run stopped here' . "\n";
        }
        if (!$this->log) {
            return $out;
        }
        $Root = $Totum->getCalculateLog();
        if (in_array('flds', $this->log, true)) {
            return $out . $this->fieldsTime($Totum, $Root->getFieldLogs());
        }
        $tree = $Root->getLogsForJsTree($Totum->getLangObj());
        $found = [];
        static::errors($tree['children'] ?? [], [], $found);
        $errors = static::deepestErrors($found);
        if ($errors) {
            $out .= "\n### Errors in the log (" . count($errors) . ")\n" . implode("\n", array_map(fn($e) => '- ' . $e, array_slice($errors, 0, 20))) . "\n"
                . (count($errors) > 20 ? '… ' . (count($errors) - 20) . " more\n" : '');
        }
        $lines = [];
        static::lines($tree['children'] ?? [], 0, $lines);
        $count = count($lines);
        $out .= "\n### Log (" . $count . ' lines' . ($count > $this->limit ? ', the first ' . $this->limit : '') . ")\n";
        if (!$count) {
            return $out . "(empty: nothing was calculated with these types)\n";
        }
        $out .= implode("\n", array_slice($lines, 0, $this->limit)) . "\n";
        if ($count > $this->limit) {
            $out .= '… ' . ($count - $this->limit) . ' lines more — debug.log_limit (max ' . static::MAX_LIMIT . ') or fewer log types' . "\n";
        }
        return $out;
    }

    protected function stopSection(array $stop): string
    {
        $out = "\n### Stopped at\n" . $stop['function'] . ' (' . $stop['kind'] . ')' . ($stop['where'] ? ' · ' . static::where($stop['where']) : '') . "\n"
            . static::details($stop['details'], "\n", '- ') . "\n";
        $allow = json_encode(['allow' => [$stop['kind']]]);
        return $out . 'Nothing is done and nothing is saved. Ask the user whether to do it; then repeat with "debug": ' . $allow
            . ' (still rolled back — the action IS done) or with "debug": {"commit": true, "allow": ["' . $stop['kind'] . '"]} / without debug — for real.' . "\n"
            . 'To test further without doing it: "debug": {"mocks": [{"function": "' . $stop['function'] . '", "result": <what it returns>}]}'
            . (isset($stop['where']['field']) ? ' or a «mock ' . $stop['function'] . ': <result>» line in <agent> of the help of the field ' . $stop['where']['field'] : '') . ".\n";
    }

    protected static function where(array $where): string
    {
        $parts = [];
        foreach ($where as $k => $v) {
            $parts[] = $k . ' ' . $v;
        }
        return implode(' · ', $parts);
    }

    protected static function details(array $details, string $glue, string $prefix = ''): string
    {
        $lines = [];
        foreach ($details as $k => $v) {
            if ($v === null || $v === [] || $v === '') {
                continue;
            }
            $v = MCPMarkdown::scalar($v);
            $lines[] = $prefix . $k . ': ' . str_replace(["\r\n", "\n"], '⏎', MCPMarkdown::cut($v, static::DETAIL_LENGTH));
        }
        return implode($glue, $lines);
    }

    /**
     * jsTree of CalculateLog::getLogsForJsTree → indented markdown list; tables without calculations are dropped
     */
    protected static function lines(array $nodes, int $depth, array &$lines): void
    {
        foreach ($nodes as $node) {
            if (!is_array($node)) {
                continue;
            }
            $children = array_filter($node['children'] ?? [], 'is_array');
            if (($node['type'] ?? null) === 'table_simple' && !$children && !str_starts_with($node['text'] ?? '', 'select')) {
                continue;
            }
            $lines[] = str_repeat('  ', $depth) . '- ' . (($node['type'] ?? null) === 'error' ? '!ERR ' : '') . static::text($node, static::TEXT_LENGTH);
            static::lines($children, $depth + 1, $lines);
        }
    }

    protected static function text(array $node, int $length): string
    {
        return MCPMarkdown::cut(str_replace(["\r\n", "\n", '[[', ']]'], ['⏎', '⏎', '"', '"'], MCPMarkdown::scalar($node['text'] ?? '')), $length);
    }

    /**
     * @param array $errors [message, path of the texts of the nodes]
     */
    protected static function errors(array $nodes, array $path, array &$errors): void
    {
        foreach ($nodes as $node) {
            if (!is_array($node)) {
                continue;
            }
            if (($node['type'] ?? null) === 'error') {
                $errors[] = [static::text($node, 1000), $path];
                continue;
            }
            $text = static::text($node, 80);
            static::errors($node['children'] ?? [], $text === '' ? $path : [...$path, $text], $errors);
        }
    }

    /**
     * An error goes up to every node of its path (like the tree of web): only the deepest place of a message is listed
     */
    protected static function deepestErrors(array $errors): array
    {
        /* the deepest first, the shortest of a depth first: "Critical error while processing [[message]]" wraps the message of the same node */
        usort($errors, fn($a, $b) => [count($b[1]), mb_strlen($a[0])] <=> [count($a[1]), mb_strlen($b[0])]);
        $kept = [];
        foreach ($errors as [$message, $path]) {
            foreach ($kept as [$keptMessage, $keptPath]) {
                if (str_contains($message, $keptMessage) && array_slice($keptPath, 0, count($path)) === $path) {
                    continue 2;
                }
            }
            $kept[] = [$message, $path];
        }
        return array_map(fn($e) => MCPMarkdown::cut($e[0], 200) . ($e[1] ? ' — in: ' . implode(' › ', $e[1]) : ''), array_reverse($kept));
    }

    protected function fieldsTime(Totum $Totum, array $fields): string
    {
        uasort($fields, fn($a, $b) => $b['time'] <=> $a['time']);
        $rows = [];
        foreach (array_slice($fields, 0, $this->limit, true) as $key => $f) {
            [$tableId, $field, $type] = explode('/', $key, 3) + [null, null, null];
            $rows[] = [$Totum->getConfig()->getTableRow((int)$tableId)['name'] ?? $tableId, $field, $type, $f['cnt'], sprintf('%.5f', $f['time'])];
        }
        return "\n### Fields calculation time (" . count($fields) . ")\n"
            . ($rows ? MCPMarkdown::mdTable(['table', 'field', 'type', 'times', 'time, s'], $rows) : "(empty)\n");
    }
}
