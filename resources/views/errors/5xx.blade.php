@include('errors.layout', ['status' => isset($exception) && method_exists($exception, 'getStatusCode') ? $exception->getStatusCode() : 500])
