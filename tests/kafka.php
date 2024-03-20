<?php /** @noinspection PhpUnhandledExceptionInspection */

use App\Helpers\KafkaHelpers;
use RdKafka\Message;

require_once __DIR__ . "/../app/Helpers/KafkaHelpers.php";
$kafka = new KafkaHelpers("localhost:9092");
$kafka->topic = 'v3-domainaggregate';
//$kafka->produce(payload: json_encode(['data empty']), headers: ['whitelist_label' => '$whitelist_label']);

[$low, $high] = $wmoffsets = $kafka->watermarkOffsets();
echo "topic starting offset: $low, ending offset(EOF): $high\n";
echo "current offset position: " . $kafka->getCommittedOffset() . "\n";
/** @var Message[] $messages */
[$messages, $eof, $from_offset, $to_offset] = $m = $kafka->getMessages(10, RD_KAFKA_OFFSET_BEGINNING);
echo("count fetched, EOF, fetched start offset, fetched last offset: " . json_encode([count($messages), $eof, $from_offset, $to_offset]) . "\n");

if (count($messages)) {
    echo "message headers: " . json_encode($messages[0]->headers) . "\n";
}

$committedOffset = $kafka->getCommittedOffset();
echo "current offset position: $committedOffset\n";
