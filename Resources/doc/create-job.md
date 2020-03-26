# Creating Jobs

#### Create a job

```php
// Dependency inject the worker or fetch it from the container
$fibonacci = $container->get('App\Worker\Fibonacci');

// For Symfony 3.3, 3.4
//     $fibonacci = $container->get('AppBundle\Worker\Fibonacci');
//

// For Symfony 2, 3.0, 3.1, 3.2:
//     $fibonacci = $container->get('app.worker.fibonacci');


// Basic Examples
$fibonacci->later()->fibonacci(20);
$fibonacci->later()->fibonacciFile(20);

// Batch Example
$fibonacci->batchLater()->fibonacci(20); // Batch up runs into a single run

// Timed Example
$fibonacci->later(90)->fibonacci(20); // Run 90 seconds later

// Priority
//    Note: whether 1 == High or Low priority is configurable, but by default it is High
$fibonacci->later(0, 1); // As soon as possible, High priority
$fibonacci->later(0, 125); // Medium priority
$fibonacci->later(0, 255); // Low priority

// Advanced Usage Example:
//  (If the job is not processed by $expireTime, then don't execute it ever...)
$expireTime = time() + 3600;
$fibonacci->later()->setExpiresAt(new \DateTime("@$expireTime"))->fibonacci(20); // Must be run within the hour or not at all
```

# Create a job from the command-line

Example:
```bash
bin/console dtc:queue:create fibonacci fibonacci 20
```

##### Creat a Job using more complicated argumetns:

It's recommended to stick with primitives (string, int, float, bool, null) or arrays of primitives. Objects are not
supported as arguments. This not only promotes loose coupling between the enqueuer and the job, it allows manually
enqueuing via cli. In a real world scenario, things can go wrong in such a way you may need to manually enqueue a job.

Considering a more complicated job:

```php
class Hello extends \Dtc\QueueBundle\Model\Worker
{
    public function run(string $name = null, int $times = 1, bool $askHowTheyAre = false): int
    {
        if (null === $name) {
            $name = '';
        }

        for ($i = 0; $i < $times; $i++) {
            $message = sprintf(
                'Hello %s',
                $name
            );
            if ($askHowTheyAre) {
                $message .= ', How are you?';
            }
            print($message.PHP_EOL);
        }
        return self::RESULT_SUCCESS;
    }

    public function getName() {
        return 'hello';
    }
}
```

A cli command could enqueue the above job:

### Using JSON-encoded arguments

```bash
$ bin/console dtc:queue:create_job -j hello run '[ null, 3, true ]'
Hello, How are you?
Hello, How are you?
Hello, How are you?
$ bin/console dtc:queue:create_job -j hello run '[ "Matthew" ]'
Hello Matthew
```

### Using PHP-serialized arguments

```bash
$ bin/console dtc:queue:create_job -p hello run 'a:3:{i:0;N;i:1;i:3;i:2;b:1;}'
Hello, How are you?
Hello, How are you?
Hello, How are you?
$ bin/console dtc:queue:create_job -p hello run 'a:1:{i:0;s:7:"Matthew";}'
Hello Matthew
```

### Using interpreted arguments (beta)

```bash
$ bin/console dtc:queue:create_job --interpret-args hello run null 3 true
Hello, How are you?
Hello, How are you?
Hello, How are you?
$ bin/console dtc:queue:create_job --interpret-args hello run Matthew
Hello Matthew
```


Please note, the last `bash` argument is a `json` encoded string of arguments to be passed to the `php` method:

```json
[
    null,
    3,
    true
]
```

```json
[
    "Matthew"
]
```

in `php` this would be represented as:

```php
$job = new Hello();

$job->run(null, 3, true);

$job->run("Matthew");
```
