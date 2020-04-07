# Developing DtcQueueBundle

Some notes for developers:

## Developing on a Mac:

```bash
git clone https://github.com/mmucklo/docker-symfony.git -s symfony5-dtcqueue
cd symfony5-dtcqueue
mkdir src

# Add the .env file similar to below
# Add the docker-compose.override.yml file below
```

Sample .env file:
```bash
MYSQL_ROOT_PASSWORD=symfony
MYSQL_DATABASE=symfony
MYSQL_USER=symfony
MYSQL_PASSWORD=symfony
POSTGRES_DB=symfony
POSTGRES_USER=symfony
POSTGRES_PASSWORD=symfony
DOCKERHOST=host.docker.internal
SYMFONY_ROOT=./src
# Choose some port not in use
NGINX_PORT=8089
NGINX_TEMPLATE=symfony4.template
```

Sample docker-compose.override.yml
```yaml
version: '3.3'
services:
    rabbitmq:
        image: rabbitmq:3.6
    beanstalkd:
        image: 1maa/beanstalkd:1.10
    php:
        volumes:
            - /Users/path/to/DtcQueueBundle:/Users/path/to/DtcQueueBundle
        links:
            - beanstalkd
            - rabbitmq
        depends_on:
            - beanstalkd
            - rabbitmq
    nginx:
        volumes:
            - /Users/path/to/DtcQueueBundle:/Users/path/to/DtcQueueBundle
```

Running the docker environment:

```bash
docker-compose build .
docker-compose up -d
docker-compose exec php bash

# Inside the docker container:

# Just in case:
apt install -y vim
cd /Users/path/to/DtcQueueBundle
composer.phar self-update
php -d memory_limit=-1 $(which composer.phar) install
```

Debugging from within a Docker Container:
```
export PHP_IDE_CONFIG="serverName=localhost"
export XDEBUG_CONFIG="idekey=phpstorm"

# Now listen for connections in PHP-storm, but you first have to change the debug port to 9001 in PHPStorm's settings.
```

Running Full Suite of unit tests:
```bash
REDIS_HOST=redis MYSQL_HOST=mysql MYSQL_USER=root MYSQL_PASSWORD=symfony MYSQL_DATABASE=symfony BEANSTALKD_HOST=beanstalkd BEANSTALKD_PORT=11300 RABBIT_MQ_HOST=rabbitmq MONGODB_HOST=mongo bin/phpunit
```

### How to run a full live environment

Within the docker container as setup above:
```bash
cd /var/www/
symfony new symfony2 --full
cd symfony2
rm -rf var/cache/*
mv * ../symfony/.
mv ??* ../symfony/.
cd ../
rmdir symfony2
cd symfony

# for doctrine do this:
composer.phar require doctrine/doctrine-bundle
composer.phar require mmucklo/queue-bundle
# Answer y at the prompts
composer.phar require mmucklo/grid-bundle
# Answer y at the prompts

# Edit the configuration
vi config/packages/dtc_queue.yaml
# change
manager:
    job: orm 
# optional
timings:
    record: true
```

```
bin/console doctrine:schema:update --dump-sql
# should see a bunch of dtc_queue tables

bin/console doctrine:schema:update --force
mkdir src/Worker
```

Add this to src/Worker/Fibonacci.php
```php
<?php
namespace App\Worker;

class Fibonacci
    extends \Dtc\QueueBundle\Model\Worker
{
    private $filename;
    public function __construct() {
        $this->filename = '/tmp/fib-result.txt';
    }

    public function fibonacciFile($n) {
        $feb = $this->fibonacci($n);
        file_put_contents($this->filename, "{$n}: {$feb}");
    }


    public function fibonacci($n)
    {
        if($n == 0)
            return 0; //F0
        elseif ($n == 1)
            return 1; //F1
        else
            return $this->fibonacci($n - 1) + $this->fibonacci($n - 2);
    }

    public function getName() {
        return 'fibonacci';
    }

    public function getFilename()
    {
        return $this->filename;
    }
}
```

### Now test

```bash
bin/console dtc:queue:create_job fibonacci fibonacciFile 8
bin/console dtc:queue:run

# Check output
cat /tmp/fib-result.txt
```

You should be able to go to: [http://localhost:8089/dtc_queue/](http://localhost:8089/dtc_queue/) and see results (you may need to change the port if the port is not the same as you specified).

### Now for development

Edit the top of /var/www/symfony/src/composer.json
```json
{
    "type": "project",
    "license": "proprietary",
    "repositories": [
    {
        "type": "path",
        "url": "/Users/path/to/DtcQueueBundle",
        "options": {
            "symlink": true
        }
    }],
```
(and so on...)


Now the symlinking (don't bother re-running composer, that's just there for the future in case you pull in another bundle or delete the vendor directory, for example):

```bash
cd /var/www/symfony/vendor/mmucklo
rm -rf queue-bundle
ln -s /Users/path/to/DtcQueueBundle queue-bundle
# Not sure if this below is necessary:
ln -s /Users/path/to/DtcQueueBundle DtcQueueBundle
```

