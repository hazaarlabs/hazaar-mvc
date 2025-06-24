# CLI Tools

Hazaar provides a suite of command-line tools to help you manage, develop, and maintain both the Hazaar framework and your application. These tools are built using the powerful Hazaar console classes such as [`Hazaar\Console\Application`](/api/class/Hazaar/Console/Application.html) and [`Hazaar\Console\Module`](/api/class/Hazaar/Console/Module.html), which provide a modular and extensible foundation for CLI development. 

These CLI tools allow you to:
- Manage application configuration and structure
- Work with files, encryption, and geodata
- Generate and maintain API documentation
- Control and monitor background services and agents
- Streamline development and operational workflows for Hazaar-based projects

They are designed to be easy to use, scriptable, and consistent across platforms, making it simple to automate and manage all aspects of your Hazaar application from the command line.

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

### `help`
Display help information for a command:

```bash
hazaar help [command]
```

### `config`
View or modify the application configuration. Configuration keys use dot-notation (e.g., `database.host`).

- View config:
  ```bash
  hazaar config get app.theme
  ```
- Set config:
  ```bash
  hazaar config set app.name "My Test App"
  ```

### `create`
Create a new application object (layout, view, controller, controller_basic, controller_action, model) from a template. This helps you quickly scaffold new components for your application.

```bash
hazaar create controller MyController
hazaar create model MyModel
hazaar create view MyView
```

### `file`
Work with files and encryption. You can encrypt and decrypt files, check if a file is encrypted, and view encrypted file contents. The `check` command will set an exit code of 1 if the file is encrypted, which is useful for scripting and automation.

- Check if a file is encrypted (exit code 1 if encrypted):
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
Work with API documentation. Use this to compile source documentation into markdown for use in API documentation systems like VuePress. The `index` command can generate a VuePress sidebar index.

- Compile documentation:
  ```bash
  hazaar doc compile
  ```
- Generate documentation index (for VuePress sidebar):
  ```bash
  hazaar doc index
  ```

### `geo`
Work with the geodata database used by [`Hazaar\Util\Geodata`](/api/class/Hazaar/Util/Geodata.html). This command helps manage and cache geodata required by your application. For more information, see the [`Hazaar\Util\Geodata`](/api/class/Hazaar/Util/Geodata.html) source file.

```bash
hazaar geo
```

## Warlock CLI

The `warlock` tool is included with Hazaar and provides functionality for managing and interacting with Warlock servers and agents. You can run it from the command line like this:

```bash
$ warlock
Warlock v1.0.0
Environment: development
Hazaar Console Application
Usage: warlock [GLOBAL OPTIONS]  [COMMAND OPTIONS]
Global Options:
  --env, -e <ENV>    The environment to use.  Overrides the APPLICATION_ENV environment variable
Available Commands:
  agent       Warlock Agent Commands
  help        Display help information for a command
  restart     Restart the Warlock server
  run         Run the Warlock server
  stop        Stop the Warlock server
```

To see available commands and options, run:

```bash
$ warlock help
```

## Command Reference

### `help`
Display help information for a command:

```bash
warlock help [command]
```

### `run`
Start the Warlock server. This should be started with a configuration file specifying server options and services. Example:

```bash
warlock run /path/to/warlock-config.php
```

### `stop`
Stop the Warlock server. This is only used if the server was started in the background.

```bash
warlock stop
```

### `restart`
Restart the Warlock server. This is only used if the server was started in the background.

```bash
warlock restart
```

### `agent`
Manage the Warlock agent, which is a separate server that connects to the main Warlock server to listen for code execution messages. The agent acts as a code execution service and can run code in the form of closures—either delayed, at an interval, scheduled, or as a service. Services are stored in `/app/services`.

To see available agent subcommands and options, run:

```bash
warlock help agent
```

## Agent Subcommands

### `agent run`
Start the Warlock agent. This will connect the agent to the main Warlock server and begin listening for code execution messages.

```bash
warlock agent run
```

### `agent stop`
Stop the Warlock agent. This is only used if the agent was started in the background.

```bash
warlock agent stop
```

### `agent restart`
Restart the Warlock agent. This is only used if the agent was started in the background.

```bash
warlock agent restart
```

:::tip
Refer to the Warlock CLI output for the most up-to-date list of commands and options.
:::
