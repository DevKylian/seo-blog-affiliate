<?php

namespace App\Services;

use Illuminate\Support\Str;

final class CompetitorPricingUrlParser
{
    /**
     * @return array{competitors: string[], pricing_urls: array<string, string>}
     */
    public function parse(string $competitorsText, string $pricingUrlsText = ''): array
    {
        $competitors = [];
        $pricingUrls = [];

        foreach ($this->lines($competitorsText) as $line) {
            [$name, $url] = $this->parseLine($line);
            if ($name !== '') {
                $competitors[$this->key($name)] = $name;
            }
            if ($url !== '') {
                $resolvedName = $name !== '' ? $name : $this->inferName($url, array_values($competitors));
                $pricingUrls[$resolvedName] = $url;
                $competitors[$this->key($resolvedName)] = $resolvedName;
            }
        }

        foreach ($this->lines($pricingUrlsText) as $line) {
            [$name, $url] = $this->parseLine($line);
            if ($url === '') {
                continue;
            }

            $resolvedName = $name !== '' ? $name : $this->inferName($url, array_values($competitors));
            $pricingUrls[$resolvedName] = $url;
            $competitors[$this->key($resolvedName)] = $resolvedName;
        }

        return [
            'competitors' => array_values($competitors),
            'pricing_urls' => $pricingUrls,
        ];
    }

    /** @param array<string, string> $urls */
    public function format(array $urls): string
    {
        return collect($urls)
            ->map(fn (string $url, string $name): string => trim($name).' | '.trim($url))
            ->implode("\n");
    }

    /** @return string[] */
    private function lines(string $value): array
    {
        return collect(preg_split('/\R/u', $value) ?: [])
            ->map(fn (string $line): string => trim($line))
            ->filter()
            ->values()
            ->all();
    }

    /** @return array{0:string,1:string} */
    private function parseLine(string $line): array
    {
        preg_match('/https?:\/\/[^\s<>"\']+/iu', $line, $match);
        $url = isset($match[0]) ? rtrim($match[0], ".,;)\]") : '';
        $name = $url !== '' ? str_replace($url, '', $line) : $line;
        $name = trim($name, " \t\n\r\0\x0B-|:;=>");

        return [$name, filter_var($url, FILTER_VALIDATE_URL) ? $url : ''];
    }

    /** @param string[] $competitors */
    private function inferName(string $url, array $competitors): string
    {
        $host = (string) parse_url($url, PHP_URL_HOST);
        $hostKey = $this->key($host);

        foreach ($competitors as $competitor) {
            $competitorKey = $this->key($competitor);
            if ($competitorKey !== '' && str_contains($hostKey, $competitorKey)) {
                return $competitor;
            }
        }

        $domain = preg_replace('/^www\./i', '', $host) ?: $host;
        $domain = explode('.', $domain)[0] ?: 'Concurrent';

        return Str::headline($domain);
    }

    private function key(string $value): string
    {
        return preg_replace('/[^a-z0-9]+/u', '', Str::ascii(mb_strtolower($value))) ?: '';
    }
}
