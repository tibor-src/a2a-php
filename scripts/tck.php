<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$log = '/tmp/a2a-php-server.log';
$pid = (int) trim((string) shell_exec(
    'PHP_CLI_SERVER_WORKERS=4 php -S 127.0.0.1:9999 '.escapeshellarg($root.'/examples/server.php').' > '.escapeshellarg($log).' 2>&1 & echo $!',
));

if ($pid < 1) {
    fwrite(STDERR, "Failed to start the example server.\n");
    exit(1);
}

$ready = false;

for ($i = 0; $i < 30; $i++) {
    $card = @file_get_contents('http://127.0.0.1:9999/.well-known/agent-card.json');

    if (is_string($card) && str_contains($card, 'TCK PHP Agent')) {
        $ready = true;
        break;
    }

    usleep(200_000);
}

if (! $ready) {
    fwrite(STDERR, "Server failed to start.\n");
    fwrite(STDERR, (string) @file_get_contents($log));
    posix_kill($pid, SIGTERM);
    exit(1);
}

$tck = getenv('A2A_TCK_DIR') ?: '/tmp/a2a-tck';

if (! is_dir($tck)) {
    passthru('git clone --depth 1 https://github.com/a2aproject/a2a-tck.git '.escapeshellarg($tck), $clone);

    if ($clone !== 0) {
        posix_kill($pid, SIGTERM);
        exit($clone);
    }
}

passthru(
    'cd '.escapeshellarg($tck).' && uv venv --allow-existing && uv pip install -e . && uv run ./run_tck.py --sut-host http://127.0.0.1:9999 --transport jsonrpc,http_json --level must && uv run ./run_tck.py --sut-host http://127.0.0.1:9999 --transport jsonrpc,http_json --level should',
    $tckStatus,
);

posix_kill($pid, SIGTERM);

exit($tckStatus);
