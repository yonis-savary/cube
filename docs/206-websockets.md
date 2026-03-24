<!-- menu --><table style='width:100%'><tr><td style='width: 33%'><div style="text-align: left"><a href="./205-schedule-and-queues.md">Previous : Schedule and queues</a></div></td><td style='width: 33%; text-align: center'><div style="Center"><a href="./README.md"> Readme</a></div></td><td style='width: 33%'><div style="text-align: right"><a href="./999-large-upload.md">Next : Large upload</a></div></td></tr></table>

# Websocket (WIP Documentation)


## Websocket Architecture

To send out websocket signals from your backend,
the process has been split into 3 parts

```
 _________           ________________________           __________
| Backend |-------->| (HTTP + Socket) Server |<------->| Frontend |
|_________|         |________________________|         |__________|
```

Sending async signals with PHP can be quite a challenge, when 
sending signal from your backend, the process shall be 

0. You frontend subscribes to your server through the **Http+Socket** Server
1. Your **backend** sends a signal to the HTTP Server
2. The **Http+Socket** Hybrid server receives the signal and route it to the right Channel class
3. The channel class redirect the signal to your frontend

## Configuration & Setup

In order to use the websocket configuration, you need to configure both the 
`WebsocketConfiguration` (used by the **Http+Socket** process) and the `BroadcastConfiguration` (used by the **backend**)

### Configuration

```php
new WebsocketConfiguration(
    // Used to bind the process (Websocket-site, where the frontend connects)
    websocketHost: "0.0.0.0",
    websocketPort: 8088,
    // Used to bind the http server (where the backend connects)
    httpHost: "0.0.0.0",
    // Note: when using containers, this port should only be accessible through the backend
    httpPort: 8089,
);

new BroadcastConfiguration(
    // Address used by the backend to connect to the Http+Socket process
    // If using containers, you can set it to the Socket container name
    httpHost: 'websocket',
    // Port used to communicate
    // If left null, the WebsocketConfiguration.httpPort shall be re-used
    httpPort: null,
);
```

### Creating a channel

Here is an example of how to use channels

```php
class JobChannel extends Channel {
    public function getRoute(): string {
        return "/job/{id}";
    }
}
```

```php
class MyService {
    public function __construct(
        protected JobChannel $jobChannel
    ) {}

    public function process() {
        //...
        $jobChannel->emit(
            // Data to send to the frontend
            ["status" => "ended", "exitCode" => $exitCode],
            // Route parameters (job id in this case)
            [394898]
        )
        //...

        // Base route params can also be set
        // It is a good optimization to use if you are sending a lot of signal
        $jobChannel->lockParams([394898]);
        // Previously set params shall be always used in these calls
        // Newly given params shall be ignored until...
        $jobChannel->emit(["status" => "ended", "exitCode" => $exitCode]);
        $jobChannel->emit(["status" => "ended", "exitCode" => $exitCode]);
        $jobChannel->emit(["status" => "ended", "exitCode" => $exitCode]);
        // ...we unlock the params
        $jobChannel->unlockParams();
    }
}
```

```php

class SomeController extends Controller {
    public static function myMethod(JobChannel $jobChannel) {
        // You can also redirect your frontend user to the websocket service !
        return $jobChannel->redirect([394898]);
    }
}
```

## Launching the websocket server

Cube includes a command to launch the websocket server
```bash
php do websocket:launch
```

<!-- menu --><table style='width:100%'><tr><td style='width: 33%'><div style="text-align: left"><a href="./205-schedule-and-queues.md">Previous : Schedule and queues</a></div></td><td style='width: 33%; text-align: center'><div style="Center"><a href="./README.md"> Readme</a></div></td><td style='width: 33%'><div style="text-align: right"><a href="./999-large-upload.md">Next : Large upload</a></div></td></tr></table>
