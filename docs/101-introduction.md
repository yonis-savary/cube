<!-- menu --><table style='width:100%'><tr><td style='width: 33%'><div style="text-align: left"><a href="./README.md">Previous : Readme</a></div></td><td style='width: 33%; text-align: center'><div style="Center"><a href="./README.md"> Readme</a></div></td><td style='width: 33%'><div style="text-align: right"><a href="./102-getting-started.md">Next : Getting started</a></div></td></tr></table>

# Introduction

Cube is a simple back-end framework for PHP 8, here are some Cube specificities :
- Simple architecture
- Optimized Type Hint / PHPDoc for your IDE
- Every back-end basic features, such as
  - Routing
  - Controllers
  - Middlewares
  - Model Manipulation

Here is what your code can look like with Cube

```php
# Model handling
$user = User::insertArray([
    'email' => 'nobody@domain.com',
    'password' => $hash,
    'permissions' => [
        ['permission_id' => 1],
        ['permission_id' => 2],
        ['permission_id' => 3],
    ]
]);

$clone = $user->replicate();
User::delete()->order('id', 'ASC')->limit(5);

# Logging
Logger::getInstance()->log('Created user {user}', ['user' => $user->toArray()]);

# Fetch
$response = (new Request('GET', '/some-api'))->fetch();
$response->getBody();

# Array / Data handling
Bunch::of([1,2,3])
    ->map(fn($x) => $x*2)
    ->push(8)
    ->get();

# Scheduling
\Cube\everyMinute(function(){
    Log::getInstance()->log('Launch every 5 minutes');
}, 5);
```


<!-- menu --><table style='width:100%'><tr><td style='width: 33%'><div style="text-align: left"><a href="./README.md">Previous : Readme</a></div></td><td style='width: 33%; text-align: center'><div style="Center"><a href="./README.md"> Readme</a></div></td><td style='width: 33%'><div style="text-align: right"><a href="./102-getting-started.md">Next : Getting started</a></div></td></tr></table>
