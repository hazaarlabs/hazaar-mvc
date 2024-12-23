Hazaar Unit Testing
=======================

The /tests directory contains scripts that are designed to be executed
by the PHPUnit library to test the Hazaar framework for errors.

To execute all tests:

  phpunit --bootstrap tests/bootstrap.php tests

Alternatively you can execute a single test:

  phpunit --bootstrap tests/bootstrap.php tests/MoneyTest

If tests succeed you should see output such as:

  PHPUnit 4.0.9 by Sebastian Bergmann.

  .

  Time: 72 ms, Memory: 3.75Mb

  OK (1 test, 1 assertion)
