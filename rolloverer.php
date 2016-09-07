<?php
/**
 *  Stand alone script for process 'Rollover Pattern' Elasticsearch
 *
 * @category  PHP
 * @author    Lubomyr Grynyshyn <grynysh@gmail.com>
 */

echo 'Start: ' . date('H:i:s D M') . "\n";

if (count($argv) < 2) {
    die("Usage: rollover.php <rolloverConditions> [rolloverIndex] [host] [port] \n");
}

$conditions = json_decode($argv[1], true);

if (!$conditions) {
    die('Wrong json format for rollover conditions');
}

//Set default parameters
$rolloverIndex = isset($argv[2]) ? $argv[2] : 'active-logs';
$host = isset($argv[3]) ? $argv[3] : 'localhost';
$port = isset($argv[4]) ? $argv[4] : 9200;

$c = new Curl($host, $port);

//Rolling an index over by condition.
$rolloverResult = $c->query("$rolloverIndex/_rollover", array('conditions' => $conditions), Curl::POST);

if (isset($rolloverResult['rolled_over']) and $rolloverResult['rolled_over']) {
    $oldIndex = $rolloverResult['old_index'];
    $inactiveIndex = str_replace('active', 'inactive', $oldIndex);

    echo "Old index: $oldIndex\n";
    echo "New index: {$rolloverResult['new_index']}\n";

    //Shrinking the index
    $c->query("$oldIndex/_settings", array("index.blocks.write" => true), Curl::PUT);
    $shrinkResponse = $c->query("$oldIndex/_shrink/$inactiveIndex", array(), Curl::POST);

    if (isset($shrinkResponse['acknowledged']) and $shrinkResponse['acknowledged']) {
        //Re assign aliases
        $c->query(
            '_aliases',
            array(
                'actions' => array(
                    array(
                        'remove' => array(
                            'index' => $oldIndex,
                            'alias' => 'search-logs'
                        )
                    ),
                    array(
                        'add' => array(
                            'index' => $inactiveIndex,
                            'alias' => 'search-logs'
                        )
                    )
                )
            ),
            Curl::POST
        );

        //Saving space
        $c->query("$inactiveIndex/_forcemerge?max_num_segments=1", array(), Curl::POST);
        $c->query("$inactiveIndex/_settings", array("number_of_replicas" => 1), Curl::PUT);
        $delResponse = $c->query($oldIndex, array(), Curl::DELETE);

        echo isset($delResponse['acknowledged']) and $delResponse['acknowledged'] ? "Index $oldIndex deleted\n" : "Index $oldIndex was not deleted!\n";
    } else {
        echo "Index $oldIndex not shrinked!";
    }

} else {
    echo "No time for rollover with conditions: $argv[1]\n";
}

echo 'End: ' . date('H:i:s D M') . "\n";

/**
 * Simple curl wrapper
 *
 * Class Curl
 */
class Curl {

    /**
     * ES host
     * @var string
     */
    private $host;

    /**
     * ES port
     * @var int
     */
    private $port;

    const POST='POST';
    const GET='GET';
    const PUT='PUT';
    const DELETE='DELETE';

    /**
     * @param $host
     * @param $port
     */
    public function __construct($host, $port) {
        $this->host = $host;
        $this->port = $port;
    }

    /**
     * @param $url
     * @param array $parameters
     * @param string $method
     * @return bool|array
     */
    public function query($url, $parameters = array(), $method = self::GET) {
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, "$this->host/$url");
        curl_setopt($ch, CURLOPT_PORT, $this->port);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, strtoupper($method));

        if (!empty($parameters)) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($parameters));
        }

        $result = curl_exec($ch);
        curl_close($ch);

        if ($result === false) {
            return false;
        } else {
            return json_decode($result, true);
        }
    }
}
