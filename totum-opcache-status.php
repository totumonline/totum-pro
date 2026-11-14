<?php
header('Content-Type: text/plain; charset=UTF-8');

$showScripts = isset($_GET['scripts']);
$status = opcache_get_status($showScripts);

if (!isset($status['memory_usage'])) {
    exit("OPcache shared memory statistics are unavailable\n");
}

$memory = $status['memory_usage'];
$mib = 1024 ** 2;

printf("OPcache: %.2f MiB used, %.2f MiB free, %.2f MiB wasted\n",
    $memory['used_memory'] / $mib,
    $memory['free_memory'] / $mib,
    $memory['wasted_memory'] / $mib
);
printf("Cached scripts: %d\n", $status['opcache_statistics']['num_cached_scripts']);

$jit = $status['jit'] ?? null;
if ($jit !== null) {
    printf("JIT: %s, %.3f of %.3f MiB used\n",
        $jit['on'] ? 'On' : 'Off',
        ($jit['buffer_size'] - $jit['buffer_free']) / $mib,
        $jit['buffer_size'] / $mib
    );
}

if ($showScripts) {
    $scripts = $status['scripts'] ?? [];
    usort($scripts, fn($a, $b) => $b['memory_consumption'] <=> $a['memory_consumption']);
    foreach (array_slice($scripts, 0, 15) as $script) {
        printf("%8.1f KiB  %s\n", $script['memory_consumption'] / 1024, $script['full_path']);
    }
}