% Z

## Introduction ##
Z is a metaprogramming system, especially useful for dependency-based build and deploy management. It was originally 
designed for PHP-based projects. 

It utilizes a simple declarative DSL, based on YML, to define tasks and configuration. The configuration can be used 
(and validated) by plugins, that provide their own set of tasks and / or language extensions.

* [FAQ](FAQ.html)
* [tutorial](tutorial.html)
* [roadmap](roadmap.html)
* [Running Z files standalone](standalone.html)
* [Reference](reference.html)

## Task definition ##

```
tasks:
    namespace.taskname:
        args:
            foo: "value"            # A string value variable that is injected into the execution scope
            bar: ? "default-value"     # A string value that is overridable and defaults to "default-value"
            baz: ?                     # A variable that is required by the task

        flags:
            foo: false              # The value of 'foo' will be false, unless --with-foo is passed to the script
            bar: true               # The value of 'bar' will be true, unless --no-bar is passed to the script

        # if the expression evaluates to true, the task´s body and triggers are skipped.
        # Prerequisites are called no matter the outcome of the expression
        unless: expression

        # If the expression does not evaluate to true, an exception is thrown.
        # This will be caught in "preflight" stage, in which the entire script is evaluated without executing actual
        # commands
        assert: expression

        # prerequisites
        pre:
            - @another.task
            - Some shell script make use of variable $(foo)
            - @(if condition) Some conditional shell script

        # task body
        do:
            - @another.task
            - Some shell script with a $(variable)
            - @(if condition) Some conditional shell script

        # task triggers
        post:
            - @another.task
            - Some shell script
            - @(if condition) Some conditional shell script
```

Tasks are defined by prerequisites, a body identified by the "do" section and task triggers, which trigger commands
and/or other tasks right after the task is executed.

Read the [tutorial](tutorial.html) for a more detailed introduction.