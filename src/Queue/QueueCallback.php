<?php 

namespace Cube\Queue;

class QueueCallback
{
    protected $function;

    public function __construct(
        callable $function,
        protected mixed $args
    ){
        // Sorry for that ! Tried to put it in function parameters but ...
        // Property Cube\Queue\QueueCallback::$function cannot have type callable
        $this->function = $function;
    }

    public function __serialize(): array
    {
        return ["callback" => $this->function, "args" => $this->args];
    }

    public function __unserialize(array $data): void
    {
        $this->function = $data['callback'];
        $this->args = $data['args'];
    }

    public function __invoke()
    {
        ($this->function)($this->args);
    }
}