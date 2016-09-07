# elasticsearch-rollover
PHP script for Elasticsearch rollover API

Info
-----
Read more about the [Rollover Pattern](https://www.elastic.co/guide/en/elasticsearch/reference/master/indices-rollover-index.html)

```
rollover.php <rolloverConditions> [rolloverIndex] [host] [port]
```
Parameters:
1. rolloverConditions - Required. JSON string with rollover API condition. Example :
    ```
    {
      "conditions": {
        "max_age":   "7d",
        "max_docs":  1000
      }
    }
    ```
2. rolloverIndex - Optional, default value `active-logs`. Active index alias.
3. host - Optional, default value `localhost`. ES host.
4. port - Optional, default value `9200`. ES port.

Usage
-----
```
php rolloverer.php '{"max_docs":  5}'
```

```
php rolloverer.php '{"max_docs":  5, "max_age":   "1d"}' indexName
```
