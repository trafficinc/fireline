<?php

namespace Handlers;

interface Handler
{
    public function setNext(Handler $handler): Handler;

    public function handle(string $type, array $request): ?string;
}