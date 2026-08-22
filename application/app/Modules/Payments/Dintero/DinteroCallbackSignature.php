<?php

namespace App\Modules\Payments\Dintero;

use Illuminate\Http\Request;

final class DinteroCallbackSignature
{
    public function isValid(Request $request): bool
    {
        $secret = trim((string) config('payments.dintero.callback_secret'));
        $required = (bool) config('payments.dintero.require_callback_signature', false);
        if ($secret === '') {
            return ! $required;
        }

        $header = trim((string) $request->header('Dintero-Signature'));
        if (preg_match('/^t=(\d+),v0-hmac-sha256=([a-f0-9]{64})$/', $header, $matches) !== 1) {
            return false;
        }
        $timestamp = (int) $matches[1];
        if (abs(time() - $timestamp) > 300) {
            return false;
        }

        $query = $request->query();
        ksort($query, SORT_STRING);
        $encodedQuery = http_build_query($query, '', '&', PHP_QUERY_RFC1738);
        $payload = implode("\n", [
            (string) $timestamp,
            (string) app(DinteroConfiguration::class)->accountId,
            strtoupper($request->method()),
            strtolower($request->getHost()),
            '/'.ltrim($request->getPathInfo(), '/'),
            $encodedQuery,
        ]);
        $expected = hash_hmac('sha256', $payload, $secret);

        return hash_equals($expected, $matches[2]);
    }
}
