<?php

//define('DEBUG', false);

// Call this at each point of interest, passing a descriptive string
function prof_flag($str)
{
    global $prof_timing, $prof_names;
    $prof_timing[] = microtime(true);
    $prof_names[] = $str;
}

// Call this when you're done and want to see the results
function prof_print()
{
    global $prof_timing, $prof_names;
    $size = count($prof_timing);
    for($i=0;$i<$size - 1; $i++)
    {
        echo "{$prof_names[$i]}\n";
        echo sprintf("time:%f\n", $prof_timing[$i+1]-$prof_timing[$i]);
    }
    echo "{$prof_names[$size-1]}\n";
}