<!-- menu --><table style='width:100%'><tr><td style='width: 33%'><div style="text-align: left"><a href="./204-http-client.md">Previous : Http client</a></div></td><td style='width: 33%; text-align: center'><div style="Center"><a href="./README.md"> Readme</a></div></td><td style='width: 33%'><div style="text-align: right"><a href="./206-websockets.md">Next : Websockets</a></div></td></tr></table>

# Schedule and Queues

(WIP)

## Queues 

Queues are a great way to process data asynchronously

To create a queue, create a Class extending the abstract `Queue` class

```php
class CalculatorQueue extends Queue {
    public function __invoke(int $a, int $b) {
        $this->logger->info($a + $b);
    }
}
```

Then, you can push data either by using `push` or `queue`

```php
CalculatorQueue::queue(2,3);
// OR
$queue = new CalculatorQueue();
$queue->push(2, 3);
```

Finally, you can launch your queue with the `queue` command

```bash
php do cube:queue --queue=CalculatorQueue

# You can use the -l flag to attach the global logger to stdOut
# Useful when launched as docker service
php do cube:queue --queue=CalculatorQueue -l
```

In our example, you will notice that a `calculatorqueue.csv` got created in `Storage/Logs`, this file is used
as global log file as long as your queue runs

## Customizing Queue class 

You can customize your queue behavior by overriding these methods

```php
class CalculatorQueue extends Queue {
    /**
     * You can edit the way the Queue store jobs
     * Using Redis is advised for medium|large-sized applications
     * (by default the local disk is used)
     * You can find a basic Redis docker service at the end of this document
     */
    protected function getDriver(): QueueDriver {
        return new RedisQueue();
    }

    /**
     * This method shall be called when a exception is raised
     * when processing a queue item
     *
     * @return bool If `true`, the system will re-push the failed job to the queue, otherwise, the job is cancelled
     */
    protected function onError(Throwable $thrown, array $args): bool
    {
        // In this exemple, we log the exception and delete the failed job
        $this->logger->logThrowable($thrown);
        return false;
    }

    public function __invoke(int $a, int $b) {
        $this->logger->info($a + $b);
    }
}
```

## Clearing/Flushing Queue 

To clear your Queue, you can either call the `queue` command with the `-f` flag, or call the `flush` method 

```bash
php do cube:queue --queue=CalculatorQueue -f
```

or

```php
$calculatorqueue->flush();
```

<!-- menu --><table style='width:100%'><tr><td style='width: 33%'><div style="text-align: left"><a href="./204-http-client.md">Previous : Http client</a></div></td><td style='width: 33%; text-align: center'><div style="Center"><a href="./README.md"> Readme</a></div></td><td style='width: 33%'><div style="text-align: right"><a href="./206-websockets.md">Next : Websockets</a></div></td></tr></table>
