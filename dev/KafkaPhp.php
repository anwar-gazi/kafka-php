<?php
// phpstorm inspection
/** @noinspection PhpUnused */
/** @noinspection DuplicatedCode */
/** @noinspection PhpUndefinedClassInspection for phpStorm lacking php-rdkafka stubs */

namespace Kafka;

use Exception;
use JetBrains\PhpStorm\Deprecated;
use JetBrains\PhpStorm\NoReturn;
use RdKafka\Conf;
use RdKafka\Consumer;
use RdKafka\ConsumerTopic;
use RdKafka\KafkaConsumer;
use RdKafka\Message;
use RdKafka\Producer;
use RdKafka\TopicConf;
use RdKafka\TopicPartition;

/**
 * @version 1.0.0
 * @author MinhajulAnwar email:minhaj.me.bd@gmail.com 15-MAR-2024
 * @requires php-rdkafka https://github.com/arnaud-lb/php-rdkafka even though I plan to phase it out
 *
 * @property $topic getter/setter
 * @property $partition getter/setter
 * @property $group_instance_id getter/setter the consumer instance id
 */
class KafkaPhp
{
    private const int msgSize = 205000000;

    /**
     * keys are our own
     * @var array
     */
    private array $opts = [
        'topic' => 'default',
        'partition' => 0,
    ];

    private array $producerConfList = [
        'bootstrap.servers' => null,
        'queue.buffering.max.ms' => '1', // send messages asap instead of waiting for batch, also improves shutdown time, https://github.com/arnaud-lb/php-rdkafka?tab=readme-ov-file#queuebufferingmaxms
        'message.max.bytes' => self::msgSize, //remember, this looks same as the broker config even though setting this in kafka/config/server.properties has no effect,
    ];

    private array $consumerConfList = [
        'bootstrap.servers' => null,
        'receive.message.max.bytes' => self::msgSize,
        //'allow.auto.create.topics' => 'true',//requires broker(server.properties) auto.create.topics.enable=true; warning: may cause partition explosion with replication factor
        //'max.partition.fetch.bytes' => self::msgSize,//shouldn't be lower than broker settings message.max.bytes
        //'fetch.max.bytes' => self::msgSize,
        //'fetch.message.max.bytes' => self::msgSize,
        //'log_level' => null,
        //'debug' => 'all',
        'group.id' => 'default',
        //'group.instance.id' => '', //for static membership(no rebalance for leave inside session timeout) set it unique for each consumer
        //'session.timeout.ms' => '',
        'auto.offset.reset' => 'earliest',// earliest, latest, end, error
        'enable.auto.commit' => 'false', // should be true for enable.auto.offset.store=false, https://github.com/confluentinc/librdkafka/blob/a6d85bdbc1023b1a5477b8befe516242c3e182f6/INTRODUCTION.md?plain=1#L1466
        'enable.auto.offset.store' => 'false',
        //'auto.commit.interval.ms' => '30000',
        //'retention.bytes' => '1000',
        'enable.partition.eof' => 'true',
        'fetch.wait.max.ms' => '500',
        //'socket.timeout.ms' => '50',
    ];

    private array $topicConfList = [

    ];

    /**
     * these values do not affect from here. They are listed for your awareness. Set them in server.properties file
     * @var array
     * @noinspection PhpUnusedPrivateFieldInspection
     */
    private array $brokerConfList = [
        //'message.max.bytes' => self::msgSize, //server.properties. Not required if you set this property in the producer config
        //'max.message.bytes' => self::msgSize, // topic global settings. defaults to message.max.bytes
        //'max.request.size' => self::msgSize, //set in producer.properties even though I've seen that has no effect, probably defaults to producer client conf message.max.bytes
        //'replica.fetch.max.bytes' => self::msgSize, // crucial. for inconsistent value, fails silent, https://stackoverflow.com/questions/21020347/how-can-i-send-large-messages-with-kafka-over-15mb
        //'fetch.max.bytes' => self::msgSize, // determines batch size, automatically adjusted upwards to be at least message.max.bytes
        //'replica.fetch.response.max.bytes' => self::msgSize,
        //'retention.ms' => '100000', // committed messages retention policy
        //'socket.request.max.bytes' => self::msgSize, //server.properties
        //'segment.bytes' => self::msgSize, //server.properties,
        //'batch.size' => '',
    ];

    /**
     * @var KafkaConsumer used for __destruct
     * @noinspection PhpUnusedPrivateFieldInspection
     */
    private KafkaConsumer $consumer;

    /**
     * @var Producer used for __destruct
     * @noinspection PhpUnusedPrivateFieldInspection
     */
    private Producer $producer;

    /**
     * @param string $bootstrap_servers usually comes from your project env file, e.g., env('KAFKA_BROKERS')
     */
    function __construct(string $bootstrap_servers)
    {
        $this->producerConfList['bootstrap.servers'] = $bootstrap_servers;
        $this->consumerConfList['bootstrap.servers'] = $bootstrap_servers;
    }

    /*
    function __destruct()
    {
        if ($this->producer) $this->producer->flush(1000 * 30);
        if ($this->consumer) {
            $this->consumer->unsubscribe();
            $this->consumer->close();
        }
    }
    */

    /**
     * @throws Exception if $key not implemented
     */
    public function __get(string $key): mixed
    {
        if ($key === 'topic') return $this->opts['topic'];
        if ($key === 'partition') return $this->opts['partition'];
        if ($key === 'group_instance_id') return $this->consumerConfList['group.instance.id'];
        throw new Exception("undefined key");
    }

    /**
     * @throws Exception if $key not implemented
     */
    public function __set(string $key, string $value): void
    {
        if ($key === 'topic') {
            $this->opts['topic'] = $value;
            return;
        }
        if ($key === 'partition') {
            $this->opts['partition'] = $value;
            return;
        }
        if ($key === 'group_instance_id') {
            $this->consumerConfList['group.instance.id'] = $value;
            return;
        }
        throw new Exception("undefined key");
    }

    public function consumerConf(): Conf
    {
        $conf = new Conf();
        $conf->setRebalanceCb(function () {
            //$args = func_get_args();
            //self::kafkaRebalanceCallback(...$args);
        });
        $conf->setOffsetCommitCb(function () {
            //echo("commit(OffsetCommitCb)...\n");
        });
        foreach ($this->consumerConfList as $key => $value) {
            $conf->set($key, $value);
        }
        return $conf;
    }

    public function producerConf(): Conf
    {
        $conf = new Conf();
        foreach ($this->producerConfList as $key => $value) {
            $conf->set($key, $value);
        }
        return $conf;
    }

    public function topicConf(): TopicConf
    {
        $conf = new TopicConf();
        foreach ($this->topicConfList as $key => $value) {
            $conf->set($key, $value);
        }
        return $conf;
    }

    /**
     * @noinspection PhpUnused
     */
    public function topicExists($topicName): bool
    {
        $consumer = new KafkaConsumer($this->consumerConf());
        /** @var ConsumerTopic $topic */
        $topic = $consumer->newTopic($topicName);
        $metadata = $consumer->getMetadata(true, $topic, 5000);
        $exists = $metadata->getTopics()->count() !== 0;
        $consumer->unsubscribe();
        $consumer->close();
        return $exists;
    }

    /**
     * @throws RdKafka\Exception
     * @noinspection PhpUndefinedMethodInspection
     * @noinspection PhpUnused
     * @noinspection PhpUndefinedNamespaceInspection
     * @see https://arnaud.le-blanc.net/php-rdkafka-doc/phpdoc/rdkafka-kafkaconsumer.getmetadata.html
     * @deprecated doesn't actually create a topic as topic doesn't have method newPartitions
     * @warning not optimised for kafka cluster, fetching from local only. e.g., getMetadata(false ...)
     * @warning assigns only one partition
     */
    public function createTopic(string $topicName, int $partitionCount = 1): void
    {
        $topicReady = null;
        $consumer = new KafkaConsumer($this->consumerConf());
        /** @var ConsumerTopic $topic */
        $topic = $consumer->newTopic($topicName);
        $metadata = $consumer->getMetadata(false, $topic, 60e3); // get local data (getMetadata first param false), avoiding cluster
        /** @var RdKafka\Metadata\Topic $topicM */
        foreach ($metadata->getTopics() as $topicM) {
            if ($topicM->getTopic() === $topicName && $topicM->getPartitions()->count() > 0) $topicReady = true; // also $topicM->err===RD_KAFKA_RESP_ERR_TOPIC_EXCEPTION
        }
        if (!$topicReady) {
            //echo "topic not found, creating...\n";
            $topic->newPartitions($partitionCount)->create();
        }
        $consumer->unsubscribe();
        $consumer->close();
    }

    /**
     * @throws Exception with code 6; for invalid filepath
     */
    public function produceFile(string $filepath): void
    {
        if (!is_file($filepath)) {
            throw new Exception("invalid file $filepath", 6);
        }
        $payload = file_get_contents($filepath);
        $this->produce(payload: $payload, headers: ['filename' => basename($filepath), 'filesize' => filesize($filepath)]);
    }

    /**
     * @param mixed $payload
     * @param array $headers
     * @return void
     * @note for auto.create.topics.enable=true at kafka/config/server.properties, produce can create topic automatically
     * @throws \RdKafka\Exception when topic doesn't exist
     * @noinspection PhpDocRedundantThrowsInspection phpstorm mistakenly warns that this function doesn't throw the exception, which it actually does
     */
    public function produce(mixed $payload, array $headers = []): void
    {
        $producer = new Producer($this->producerConf());
        $topic = $producer->newTopic($this->opts['topic'], $this->topicConf());
        $topic->producev(partition: $this->opts['partition'], msgflags: 0, payload: $payload, headers: $headers);
        $producer->flush(1000 * 30);
    }

    /**
     * preferred way to commit
     * @param Message $message
     * @return void
     */
    public function commit(Message $message): void
    {
        $consumer = new KafkaConsumer($this->consumerConf());
        $consumer->subscribe([$this->opts['topic']]);
        $consumer->commit($message);
    }

    /**
     * another way of commit
     * incomplete
     * @param int $offset
     * @return void
     * @deprecated untested
     * @noinspection PhpUnused
     */
    #[Deprecated] public function offsetStore(int $offset): void
    {
        $consumer = new Consumer($this->consumerConf());
        $topic = $consumer->newTopic($this->opts['topic'], $this->topicConf());
        $topic->offsetStore($this->opts['partition'], $offset);
    }

    /**
     * actually it returns current offset
     * @return int
     * @issue shows no error when there is no existing consumer topic
     */
    public function getCommittedOffset(): int
    {
        $consumer = new KafkaConsumer($this->consumerConf());
        /** @var TopicPartition[] $offsets */
        $offsets = $consumer->getCommittedOffsets([new TopicPartition($this->opts['topic'], $this->opts['partition'])], 10000);
        return $offsets[0]->getOffset();
    }

    /**
     * @return int[]
     * @throws \RdKafka\Exception when partitions not assigned for topic or topic doesn't exist
     */
    public function watermarkOffsets(): array
    {
        $consumer = new KafkaConsumer($this->consumerConf());
        $low = null;
        $high = null;
        $consumer->queryWaterMarkOffsets($this->opts['topic'], $this->opts['partition'], $low, $high, 1000);
        return [$low, $high];
    }

    /**
     * @return int
     * @deprecated buggy, unreliable
     * @noinspection PhpUnused
     */
    #[Deprecated] public function getOffsetPosition(): int
    {
        $consumer = new KafkaConsumer($this->consumerConf());
        /** @var TopicPartition[] $topicPartitions */
        $topicPartitions = $consumer->getOffsetPositions([new TopicPartition($this->opts['topic'], $this->opts['partition'])]);
        return $topicPartitions[0]->getOffset();
    }

    #[Deprecated] public function seek(): void
    {
    }

    /**
     * incomplete
     * @param int $offset
     * @return void
     * @deprecated untested
     * @noinspection PhpUnused
     */
    #[Deprecated] public function _partitionOffsetStore(int $offset): void
    {
        new TopicPartition($this->opts['topic'], $this->opts['partition'], $offset);
    }

    /**
     * with php-rdkafka low level api
     * @param int $count excluding the EOF. To get all, pass $count=0
     * @param int $from_offset RD_KAFKA_OFFSET_STORED|RD_KAFKA_OFFSET_BEGINNING or specific offset(>=0)
     * @return array [Message[] $messages, null/true $eof, int(param $from_offset) $from_offset, int $to_offset] $eof: is topic EOF reached? $from_offset: provided parameter $to_offset: offset of the last message in the list
     * @notice the returned $messages doesn't contain topic EOF as last message, as the library arnaudlb/php-rdkafka does. Our motto here is that 'message is message, eof is eof'.
     * @notice the returned $eof can be true/null as you can assume for its purpose; it returns null when topic EOF is not reached. Null signals implicitly that eof not reached when it is falsy(null/false); and signals explicitly that eof reached when it is true. The purpose of null is to signal that we did not reach the eof, infact we may be far away from it. Null is to mean that, after fetching the $count number of messages, we still have no information about eof.
     * @notice the returned $from_offset is actually the parameter $from_offset. So, $from_offset is not exactly the first offset of the returned message. This is from a logical standpoint that the function aleady has a variable named $from_offset, so use it in the returned dataset. This also improves debuggablity.
     * @notice the returned $to_offset is exactly the offset of the last message. arnaudlb/Php-rdkafka returns EOF as a message also, but we do not do it.
     * Our logical standpoint is that, message is message, eof is eof. That's why this function also returns data(boolean/null) as $eof. Remember, if you commit the returned last message offset, then the current offset position will be that offset + 1.
     * @notice Message[] list can be empty when no message found, EOF is not returned as a message
     * @notice uses rdkafka/queue
     * @issue [resolved] sometimes doesn't set consumed offset mark, other times does (see KafkaConsumer::getOffsetPositions)
     * @issue [resolved] interestingly, when $from_offset=RD_KAFKA_OFFSET_BEGINNING with offset storage enabled(enable.auto.offset.store) yet auto commit disabled, message offsets are not stored to the last fetched message. But when $from_offset=RD_KAFKA_OFFSET_STORED, it does
     * @issue shows no error and creates no actual topic when there is no existing consumer topic
     * @notice we are using rdkafka/queue, which can be slightly faster for batch consume
     * @todo check $message->err other codes like RD_KAFKA_RESP_ERR_NO_ERROR
     */
    public function getMessages(int $count = 0, int $from_offset = RD_KAFKA_OFFSET_STORED): array
    {
        $messages = [];
        $lowConsumer = new Consumer($this->consumerConf());
        $lowTopic = $lowConsumer->newTopic($this->opts['topic'], $this->topicConf());
        $queue = $lowConsumer->newQueue();
        $lowTopic->consumeQueueStart($this->opts['partition'], $from_offset, $queue);
        $eof = null;
        $to_offset = null;
        for ($i = 0; !$count || $i < $count; $i++) {
            $message = $queue->consume(1000);
            if (null === $message || $message->err === RD_KAFKA_RESP_ERR__PARTITION_EOF) {
                $eof = true;
                unset($message);
                break;
            } else {
                $to_offset = $message->offset;
                $messages[] = $message;
            }
        }
        return [$messages, $eof, $from_offset, $to_offset];
    }

    /**
     * get one
     * @param int $from_offset
     * @return array
     * @see getMessages method
     */
    public function getMessage(int $from_offset = RD_KAFKA_OFFSET_STORED): array
    {
        $messages = [];
        $lowConsumer = new Consumer($this->consumerConf());
        $lowTopic = $lowConsumer->newTopic($this->opts['topic'], $this->topicConf());
        $lowTopic->consumeStart($this->opts['partition'], $from_offset);
        $eof = null;
        $to_offset = null;
        /** @var Message $message */
        $message = $lowTopic->consume($this->opts['partition'], 1000);
        if (null === $message || $message->err === RD_KAFKA_RESP_ERR__PARTITION_EOF) {
            $eof = true;
            unset($message);
        } else {
            $to_offset = $message->offset;
            $messages[] = $message;
        }
        $lowTopic->consumeStop($this->opts['partition']);
        return [$messages, $eof, $from_offset, $to_offset];
    }

    /**
     * @noinspection PhpUnused
     * @noinspection PhpUnusedLocalVariableInspection
     */
    public function getVeryFirstMessage(): Message
    {
        [$messages, $eof, $from_offset, $to_offset] = $this->getMessage(RD_KAFKA_OFFSET_BEGINNING);
        return $messages[0];
    }

    /**
     * @throws Exception always, because you cannot actually delete
     * @noinspection PhpUnused
     * @noinspection PhpUnusedParameterInspection
     */
    #[Deprecated] public function deleteMessage(Message $message)
    {
        throw new Exception("Kafka committed messages cannot be directly deleted. Once messages are committed, they are handled by the log retention policy.
        Set this for topic with retention.ms or retention.bytes");
    }

    /**
     * @param KafkaConsumer $consumer
     * @param bool $commit
     * @return void
     * @noinspection PhpUnused
     */
    #[NoReturn] public function kafkaConsumerShutdownHandler(KafkaConsumer $consumer, bool $commit): void
    {
        echo "***Shutting down(unsubscribe,close)...\n";
        if ($commit) {
            echo "committing...\n";
            $consumer->commit();
        } else {
            echo "no commit\n";
        }
        $consumer->unsubscribe();
        $consumer->close();
        exit(0);
    }

    /**
     * @param KafkaConsumer $consumer
     * @param int $err
     * @param array|null $partitions
     * @throws Exception
     * @noinspection PhpUnused
     */
    public function kafkaRebalanceCallback(KafkaConsumer $consumer, int $err, array $partitions = null): void
    {
        echo "rebalance...\n";
        switch ($err) {
            case RD_KAFKA_RESP_ERR__ASSIGN_PARTITIONS:
                echo "rebalance: assign...\n";
                $consumer->assign($partitions);
                break;
            case RD_KAFKA_RESP_ERR__REVOKE_PARTITIONS:
                echo "rebalance: revoke...\n";
                $consumer->assign(null);
                break;
            default:
                throw new Exception("rebalance: unknown error-code: " . $err);
        }
    }
}
