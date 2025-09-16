<!-- menu --><table style='width:100%'><tr><td style='width: 33%'><div style="text-align: left"><a href="./104-commands.md">Previous : Commands</a></div></td><td style='width: 33%; text-align: center'><div style="Center"><a href="./README.md"> Readme</a></div></td><td style='width: 33%'><div style="text-align: right"><a href="./106-routing-and-middlewares.md">Next : Routing and middlewares</a></div></td></tr></table>

# Logging

Logging with cube is pretty straightforward, it is made through the `Logger` component and respect the [PSR-3 Recommandation](https://www.php-fig.org/psr/psr-3/).

Here is an example 

```php
$logger = Logger::getInstance();

// PSR-3 Methods
$logger->debug("Some message");
$logger->info("Some message");
$logger->notice("Some message");
$logger->warning("Some message");
$logger->error("Some message");
$logger->critical("Some message");
$logger->alert("Some message");
$logger->emergency("Some message");

$logger->log('custom-level', 'some message');

$someCustomLogger = new Logger('error-logs.csv'); // Stored in Storage/Logs
// Every error message from $logger shall be logged by $someCustomLogger too!
// (Can takes any LoggerInterface object !)
$someCustomLogger->attach($logger, ['warning', 'error', 'critical', 'alert', 'emergency']);

try { /*...*/ }
catch(Throwable $err) {
    // Print the full stacktrace and useful informations
    // Done by default on fatal errors
    $logger->logThrowable($err); 
}

```

By default, every logs goes in `Storage/Logs/cube.csv`, the CSV format was used to make the log processing easier.

<!-- menu --><table style='width:100%'><tr><td style='width: 33%'><div style="text-align: left"><a href="./104-commands.md">Previous : Commands</a></div></td><td style='width: 33%; text-align: center'><div style="Center"><a href="./README.md"> Readme</a></div></td><td style='width: 33%'><div style="text-align: right"><a href="./106-routing-and-middlewares.md">Next : Routing and middlewares</a></div></td></tr></table>
