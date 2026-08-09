<?php

namespace Modules\Commerce\Services\Store;

use Modules\Store\Models\Store;
use Modules\Store\Models\StoreDomain;

class StoreDomainVerifier
{
    public function verify(StoreDomain $domain, Store $store): bool
    {
        if ($domain->store_id !== $store->id) {
            throw new \InvalidArgumentException('Domain does not belong to the current store.');
        }

        if ($domain->verified_at !== null) {
            return true;
        }

        $cnameTarget = $this->normalizeHostname("{$store->slug}.rivaify.com");
        $cnameRecords = $this->records($domain->domain, DNS_CNAME);
        $hasCname = collect($cnameRecords)->contains(function (array $record) use ($cnameTarget): bool {
            return $this->normalizeHostname((string) ($record['target'] ?? '')) === $cnameTarget;
        });

        $txtValue = $this->txtValue($store);
        $txtRecords = $this->records('_rivaify-verification.'.$domain->domain, DNS_TXT);
        $hasTxt = collect($txtRecords)->contains(function (array $record) use ($txtValue): bool {
            $value = (string) ($record['txt'] ?? implode('', $record['entries'] ?? []));

            return hash_equals($txtValue, trim($value));
        });

        if (! $hasCname && ! $hasTxt) {
            return false;
        }

        $domain->forceFill(['verified_at' => now()])->save();

        return true;
    }

    public function txtValue(Store $store): string
    {
        return 'rivaify-site-verification='.$store->ulid;
    }

    /** @return array<int, array<string, mixed>> */
    protected function records(string $hostname, int $type): array
    {
        $records = @dns_get_record($hostname, $type);

        return is_array($records) ? $records : [];
    }

    private function normalizeHostname(string $hostname): string
    {
        return mb_strtolower(rtrim(trim($hostname), '.'));
    }
}
