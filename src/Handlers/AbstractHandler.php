<?php

namespace Handlers;


abstract class AbstractHandler implements Handler
{
    /**
     * @var Handler
     */
    private $nextHandler;

    public function setNext(Handler $handler): Handler
    {
        $this->nextHandler = $handler;
        return $handler;
    }

    public function handle(string $filter, array $request): ?string
    {
        if ($this->nextHandler) {
            return $this->nextHandler->handle($filter, $request);
        }

        return null;
    }
}