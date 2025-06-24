# CLI Tools

Hazaar provides a number of CLI tools to help you manage your application and database. These tools are designed to be easy to use and provide a consistent interface across all platforms.

## Hazaar CLI

The `hazaar` tool is available in the `vendor/bin` directory of your application, or globally if installed. You can run it from the command line like this:

```bash
$ hazaar
Hazaar Tool v0.3.0
Environment: development
Hazaar Console Application
Usage: hazaar [GLOBAL OPTIONS]  [COMMAND OPTIONS]
Global Options:
  --env, -e <ENV>    The environment to use.  Overrides the APPLICATION_ENV environment variable
Available Commands:
  config     View or modify the application configuration
  create     Create a new application object (view, controller or model)
  doc        Work with API documentation
  file       Work with files and encryption
  geo        Cache the geodata database file
  help       Display help information for a command
```

To see available commands and options, run:

```bash
$ hazaar help
```

## Command Reference

### `config`
View or modify the application configuration.

- View config:
  ```bash
  hazaar config get
  ```
- Set config:
  ```bash
  hazaar config set key value
  ```

### `create`
Create a new application object (layout, view, controller, controller_basic, controller_action, model):

```bash
hazaar create controller MyController
hazaar create model MyModel
hazaar create view MyView
```

### `file`
Work with files and encryption. The following subcommands are available:

- Check if a file is encrypted:
  ```bash
  hazaar file check secure.json
  ```
- Encrypt a file:
  ```bash
  hazaar file encrypt secure.json
  ```
- Decrypt a file:
  ```bash
  hazaar file decrypt secure.json
  ```
- View the contents of an encrypted file:
  ```bash
  hazaar file view secure.json
  ```

### `doc`
Work with API documentation:

- Compile documentation:
  ```bash
  hazaar doc compile
  ```
- Generate documentation index:
  ```bash
  hazaar doc index
  ```

### `geo`
Cache the geodata database file:

```bash
hazaar geo
```

### `help`
Display help information for a command:

```bash
hazaar help [command]
```

## Warlock CLI

The `warlock` tool is included with Hazaar and provides additional functionality for managing and interacting with Warlock services.

To see available commands and options, run:

```bash
warlock help
```

### Command Reference

Below is a summary of available Warlock commands. For detailed usage of any command, run:

```bash
warlock help <command>
```

- List available Warlock services:
  ```bash
  warlock list
  ```
- Start a Warlock service:
  ```bash
  warlock start <service>
  ```
- Stop a Warlock service:
  ```bash
  warlock stop <service>
  ```
- Restart a Warlock service:
  ```bash
  warlock restart <service>
  ```
- Show the status of a Warlock service:
  ```bash
  warlock status <service>
  ```
- Show logs for a Warlock service:
  ```bash
  warlock logs <service>
  ```

Refer to the Warlock CLI output for the most up-to-date list of commands and options.
