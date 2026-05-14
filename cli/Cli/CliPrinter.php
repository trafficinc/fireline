<?php

namespace Cli;

class CliPrinter
{
    public function out($message)
    {
        echo (isset($message)) ? $message : "";
    }

    public function newline()
    {
        $this->out("\n");
    }

    public function display($message)
    {
        $this->newline();
        $this->out($message);
        $this->newline();
        $this->newline();
    }
}