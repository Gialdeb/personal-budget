<?php

/** Preserve the live proxy configuration, switching only the app-facing upstream. */
$config = json_decode(stream_get_contents(STDIN), true, 512, JSON_THROW_ON_ERROR);
$changed = 0;
$replace = function (array &$node) use (&$replace, &$changed): void {
    if (($node['handler'] ?? null) === 'reverse_proxy' && in_array('web:80', array_column($node['upstreams'] ?? [], 'dial'), true)) {
        $node = ['handler' => 'reverse_proxy', 'upstreams' => [['dial' => 'maintenance:8000']]];
        $changed++;

        return;
    }
    foreach ($node as &$value) {
        if (is_array($value)) {
            $replace($value);
        }
    }
};
$replace($config);
if ($changed === 0) {
    fwrite(STDERR, "No web:80 upstream found in the live Caddy configuration.\n");
    exit(1);
}
echo json_encode($config, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
