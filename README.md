# Cube PHP

Cube is a light framework that provide essential features for back-end web developpement. The goal is to provide every essential tools needed to make web applications and APIs.

You can find the framework's documentation in the [`/docs`](./docs/README.md) directory !

## 🧰 Features

- 🌐 Web
  - Fast Routing
  - Middleware
  - Request Validation
  - Static File Serving

- 🔩 Framework
  - PHP Configuration (With Caching)
  - CLI Command Support
  - Routine (Scheduling / Queueing)

- 🌳 Environment
  - Cache / Session / Directory manipulation
  - Logging through `psr/log`

- 💿 Data
  - Model Manipulation (Supported DBMS : MySQL, SQLite, Postgres, MariaDB)
  - Automatic Model Generation !
  - Password Authentication System

## 🔥 Installation

```bash
# Install in your repository
composer require yonis-savary/cube

# Install Server base file such a Public/, .gitignore...
cp -r vendor/yonis-savary/cube/server/* .
```


## 📈 Developpement

```sh
# Testing the framework
make test
```