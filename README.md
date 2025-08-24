# Cube PHP

Cube is a light back-end framework that provide essential features for web developpement like Routing, Model Manipulation, Caching...

## Installation

```bash
# Create a new project
composer create-project yonis-savary/cube-project MyProject

# Or install in an existing project
composer require yonis-savary/cube && cp -r vendor/yonis-savary/cube/server/* .
```

## Features

- PHP Configuration (With Caching)
- CLI Command Support
- Cache / Session / Directory manipulation
- Logging through `psr/log`
- Model Manipulation (Supported DBMS : MySQL, SQLite, Postgres, MariaDB)
- Automatic Model Generation !
- Routine (Scheduling / Queueing)
- Authentication
- Fast Routing
- Middleware
- Request Validation
- Static File Serving

## Documentation

You can read the documentation in the [`/docs`](./docs/README.md) directory !


## Developpement 

```sh
# Testing the framework
make test
```