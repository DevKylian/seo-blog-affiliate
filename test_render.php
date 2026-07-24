<?php
Auth::loginUsingId(1);
$request = Illuminate\Http\Request::create('/admin/seo-engine');
$response = app()->make(Illuminate\Contracts\Http\Kernel::class)->handle($request);

if ($response->getStatusCode() !== 200) {
    echo "Status: " . $response->getStatusCode() . "\n";
    if (isset($response->exception)) {
        echo $response->exception->getMessage() . "\n";
        echo $response->exception->getFile() . ":" . $response->exception->getLine() . "\n";
    }
} else {
    echo "OK";
}
