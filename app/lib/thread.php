<?php
/**
 * The helper functions to parallelize the process into threads.
 */

namespace thread;

/**
 * Creates parallel processes for the current script with custom parameters for each copy.
 *
 * @param array[] $params the list of params for each thread.
 * @return void
 */
function copy(array $params = []): void
{
    // Prepare basic command
    $scriptParams = $_SERVER['argv'];
    $script = ['/usr/bin/php', array_shift($scriptParams)];

    // Run thread processes
    $threads = [];
    foreach ($params as $num => $threadParams) {
        $process = proc_open([...$script, ...$threadParams], [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']], $pipes);
        $threads[$num] = [$process, $pipes];
    }

    // Waiting while processes are running
    while ($threads) {
        sleep(1);
        foreach ($threads as $num => $thread) {
            [$process, $pipes] = $thread;
            $pending = is_resource($process) && proc_get_status($process)['running'];
            if (!$pending) {
                [, $stdout, $stderr] = $pipes;

                // Result of thread process
                if ($result = stream_get_contents($stdout)) {
                    fwrite(STDOUT, $result);
                }

                // Errors output
                if ($result = stream_get_contents($stderr)) {
                    fwrite(STDERR, $result);
                }

                // Close thread resources
                foreach ($pipes as $pipe) {
                    if (is_resource($pipe)) {
                        fclose($pipe);
                    }
                }
                proc_close($process);
                unset($threads[$num]);
            }
        }
    }
}
