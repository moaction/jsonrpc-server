jsonrpc-server
==============

Server implementation for JsonRPC 2.0 protocol

http://www.jsonrpc.org/specification

Usage
-----

### Basic usage

```php
$server = new \Moaction\Jsonrpc\Server\BasicServer();
$server->addMethod('getUser', function($id) {
  return array(
    'id'   => $id,
    'name' => 'UserName'
  );
});

echo $server->run(file_get_contents('php://input'));
```

### Error reporting

Every exception in method call will be converted into error object in response.
You can specify code and message in exception.

```php
$server->addMethod('errorTest', function() {
  throw new \Exception('Strange server error', 42);
});
```

Server response will be:
```javascript
{"jsonrpc": "2.0", "error": {"code": 42, "message": "Strange server error"}, "id": null}

```

If you do not provide code, default "Server Error" code -32000 will be used. As well as error message.