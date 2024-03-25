### Run Kafka (standalone)

- download the kafka build and run, we'll use zookeeper config

Now inside the bin directory of Kafka build, execute these two commands

- `./zookeeper-server-start.sh ../config/zookeeper.properties`
- `./kafka-server-start.sh ../config/server.properties`

#### supervisor

```shell
# create user for kafka
sudo cp supervisor-kafka.conf /etc/supervisor/conf.d/kafka.conf
sudo supervisorctl reread
sudo supervisorctl update
```

#### known issues with supervisor

sometimes logs like these:<br>
%3|1709895188.108|FAIL|rdkafka#consumer-3| [thrd:GroupCoordinator]: GroupCoordinator: anwar-sultec:9092: Failed to
connect to broker at [anwar-sultec]:9092: Invalid argument (after 1ms in state CONNECT)
%3|1709895188.109|ERROR|rdkafka#consumer-3| [thrd:app]: rdkafka#consumer-3: GroupCoordinator: anwar-sultec:9092: Failed
to connect to broker at [anwar-sultec]:9092: Invalid argument (after 1ms in state CONNECT)

### docker

### PHP support: arnaud-lb/php-rdkafka https://github.com/arnaud-lb/php-rdkafka

requires rdkafka library and php extension, install with
`apt install librdkafka-dev # not sure about this one`

`apt install php8.3-rdkafka`

#### or

- install pecl support: `apt install php-dev`
- install rdkafka extension: `pecl install fdkafka`
- enable extension in active ini `extension=rdkafka.so`
- ini file for dev server: /etc/php/8.1/php.ini

### verify your php installation

list the module rdkafka with along with other modules (linux shell command): ```php -m```

get version of the installed rdkafka (open a php shell and execute)

```php
$v = phpversion("rdkafka");
echo $v;
```

### management

for docker, go inside container and remove the .sh extension and current dir marker,
e.g., `kafka-topics --bootstrap-server localhost:9092 --list`

```shell
# inside directory kafka/bin
./kafka-producer-perf-test.sh --producer-props bootstrap.servers=localhost:9092 max.request.size=105000000 --record-size 10500000 --topic default --num-records 1 --throughput 1
./kafka-topics.sh --bootstrap-server localhost:9092 --create --topic default --partitions 1 --replication-factor 1
./kafka-console-producer.sh --bootstrap-server localhost:9092 --topic default --producer-property max.request.size=105000000
./kafka-topics.sh --bootstrap-server localhost:9092 --list 
./kafka-topics.sh --bootstrap-server localhost:9092 --describe
./kafka-topics.sh --bootstrap-server localhost:9092 --describe --topic default
./kafka-topics.sh --bootstrap-server localhost:9092 --delete --topic default
./kafka-consumer-groups.sh --bootstrap-server localhost:9092 --list 
./kafka-consumer-groups.sh --bootstrap-server localhost:9092 --describe --group default
./kafka-console-consumer.sh --topic v3-domainaggregate --offset earliest --partition 0 --bootstrap-server localhost:9092
./kafka-console-consumer.sh --topic v3-domainaggregate --bootstrap-server localhost:9092
./kafka-console-consumer.sh --topic v3-domainaggregate --from-beginning --bootstrap-server localhost:9092
./kafka-console-consumer.sh --bootstrap-server localhost:9092 --topic default --from-beginning
./kafka-console-consumer.sh --bootstrap-server localhost:9092 --topic default --formatter kafka.tools.DefaultMessageFormatter --property print.timestamp=true --property print.key=true --property print.value=true --from-beginning
./kafka-console-consumer.sh --bootstrap-server localhost:9092 --topic default --formatter kafka.tools.DefaultMessageFormatter --property print.timestamp=true --property print.key=true --property print.value=false --from-beginning

./kafka-configs.sh --bootstrap-server localhost:9092 --entity-name __consumer_offsets --entity-type topics --describe --all

```

### FAQ: how to set custom message size, for very large file sending

kafka-2.13

- broker config/server.properties: `message.max.bytes=205000000` and `replica.fetch.max.bytes=205000000` which is
  slightly below 200MB
- no other config file change needed, e.g., no change in producer.properties or consumer.properties
- with your php client library(in your php code), producer config `$producerConf->set('message.max.bytes', 205000000)`
  and consumer config `$consumerConf->set('receive.message.max.bytes', 205000000)`
  see more:
- https://stackoverflow.com/questions/59322133/kafka-broker-message-size-too-large
- https://stackoverflow.com/questions/55152219/handling-large-messages-with-kafka
- https://stackoverflow.com/questions/50251660/get-kafka-compressed-message-size?rq=2
- https://stackoverflow.com/questions/21020347/how-can-i-send-large-messages-with-kafka-over-15mb
- https://stackoverflow.com/questions/77075942/kafka-large-message-error-kafkaerror-code-msg-size-too-large-val-10-str-unabl
- https://stackoverflow.com/questions/21020347/how-can-i-send-large-messages-with-kafka-over-15mb
- https://kafka.apache.org/documentation/#brokerconfigs_message.max.bytes
- https://www.conduktor.io/kafka/how-to-send-large-messages-in-apache-kafka/

### FAQ: how auto create topic

#### warning: topic auto creation can explode your topic partitions depending upon the replication factor

- producer: server.properties set `auto.create.topics.enable=true`
- consumer: config `'allow.auto.create.topics' => 'true'`

### FAQ: control auto-commit behavior 
