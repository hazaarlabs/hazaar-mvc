# CLI Tools

Hazaar provides a number of CLI tools to help you manage your application and database.  These tools are designed to be easy to use and provide a consistent interface across all platforms.

## Hazaar CLI

::: warning
The Hazzar CLI tool is still in development and will change in future releases as new features are added to the hazaar console framework.  Please check the documentation for the latest information.
:::

The `hazaar` too is available in the `vendor/bin` directory of your application.  You can run it from the command line like this:

```bash
$ vendor/bin/hazaar
Hazaar Tool v0.3.0
Environment: development

Usage: hazaar [globals] [command] [options]

Global Options:
  --env, -e - The environment to use.  Overrides the APPLICATION_ENV environment variable

Commands:
  config - View or modify the application configuration
  create - Create a new application object (view, controller or model)
  decrypt - Decrypt a file using the Hazaar encryption system
  doc - Generate API documentation
  docindex - Generate an API documentation index
  encrypt - Encrypt a file using the Hazaar encryption system
  geo - Cache the geodata database file
  help - Display help information for a command
```  

## Commands

### `config`

The `config` command is used to display the configuration for the application environment.  This is useful for debugging and understanding how the application is configured.

```bash
$ vendor/bin/hazaar config
Hazaar Tool v0.3.0
Environment: development

app.env = development
```

### `create`

The `create` command is used to create a new application object (view, controller or model).  This is useful for quickly generating the boilerplate code for a new object.

```bash
$ vendor/bin/hazaar create controller MyController
```

This will create a new controller class in the `app/controllers` directory with the name `MyController`.  The class will extend the `Hazaar\Controller` class and will include a basic constructor and index action.  The class will also include a docblock with the class name and a list of available actions.

```bash
$ vendor/bin/hazaar create model MyModel
```

This will create a new model class in the `app/models` directory with the name `MyModel`.  The class will extend the `Hazaar\Model` class and will include a basic constructor and a list of available methods.  The class will also include a docblock with the class name and a list of available methods.

```bash
$ vendor/bin/hazaar create view MyView
```

This will create a new view class in the `app/views` directory with the name `MyView`.  The class will extend the `Hazaar\View` class and will include a basic constructor and a list of available methods.  The class will also include a docblock with the class name and a list of available methods.

### `encrypt`

The `encrypt` command is used to encrypt a file using the Hazaar encryption system.  Files are encrypted in-place, so be careful when using this command.  The `encrypt` command will overwrite the original file with the encrypted version.

```bash
$ vendor/bin/hazaar encrypt secure.json
```

::: tip
Configuration files can be encrypted to protect sensitive information and will be automatically decrypted when loaded by the application.  This is useful for protecting sensitive information such as API keys, database passwords, and other sensitive information.
:::

::: warning
The `encrypt` command will overwrite the original file with the encrypted version.  Be careful when using this command.  It is recommended that your files are either backed up or version controlled before using this command.
:::

### `decrypt`

The `decrypt` command is used to decrypt a file using the Hazaar encryption system. 

```bash
$ vendor/bin/hazaar decrypt secure.json
```

### `view`

The `view` command is used to view the contents of a file, automatically decrypting it if it is encrypted.  This is useful for viewing the contents of a file without having to manually decrypt it first.

```bash
$ vendor/bin/hazaar view secure.json
```

### `doc`

The `doc` command is used to generate API documentation for the application.  Currently the only output format supported is markdown, but new formats will be added in the future.  The documentation is generated using the `Hazaar\Doc` class and is based on the docblocks in the application code.  See [Hazaar\Console\API\Documentor](/api/class/Hazaar/Console/API/Documentor.html) for more information.

```bash
$ vendor/bin/hazaar doc
```

::: note
This is the feature used to generate the documentation for the Hazaar framework itself that is available on the website.  See [API Documentation](/api/home.html) for reference.

### `docindex`

The `docindex` command is used to generate an API documentation index for the application.  This is useful for generating a list of all available classes and methods in the application.  Currently only the VuePress sidebar format is supported.

```bash
$ vendor/bin/hazaar docindex
```

::: note
This is the feature used to generate the documentation index for the Hazaar framework itself that is available on the website.  See [API Documentation](/api/home.html) for reference.
:::

### `geo`

The `cache` command is used to cache the geodata database file.  This is useful for speeding up the application when using the `Hazaar\Util\GeoData` class by preloading the database file into the runtime directory.  This command is run automatically upon first use of the `Hazaar\Util\GeoData` class, but can be run manually if needed to reduce startup time.

```bash
$ vendor/bin/hazaar cache
Hazaar Tool v0.3.0
Environment: development

Fetching geodata database file...
GeoData database file cached successfully.
Database file: /hazaar/geodata.db
Database file size: 131MB bytes
```
