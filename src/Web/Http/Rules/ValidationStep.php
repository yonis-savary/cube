<?php

namespace Cube\Web\Http\Rules;

use Cube\Utils\Text;

class ValidationStep
{
    const TYPE_CHECKER = 'check';
    const TYPE_TRANSFORMER = 'transform';

    protected mixed $errorMessage;

    public function __construct(
        public string $type,
        public mixed $callback,
        null|string|callable $errorMessage=null
    ){
        $this->errorMessage = $errorMessage;
    }

    public function __invoke(mixed &$value, ValidationReturn &$return, ?string $key=null)
    {
        if (self::TYPE_TRANSFORMER === $this->type) {
            $value = ($this->callback)($value);
            return;
        }

        if (self::TYPE_CHECKER === $this->type) {
            $stepResult = ($this->callback)($value);

            if (true !== $stepResult) {
                $errorMessage = is_callable($this->errorMessage) 
                    ? ($this->errorMessage)($value)
                    : Text::interpolate($this->errorMessage, ['key' => $key, 'value' => print_r($value, true)]);

                $return->addError($errorMessage);
            }
            return;
        }
    }
}