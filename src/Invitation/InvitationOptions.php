<?php

declare(strict_types=1);

namespace App\Invitation;

use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

final class InvitationOptions
{
    public string $name = '';
    public string $maxRequests = '10';
    public string $budgetUsd = '1';
    public string $requestsPerMinute = '2';
    public string $expiresInDays = '30';
    public string $operationId = '';
    public string $version = '';

    #[Assert\Callback]
    public function validate(ExecutionContextInterface $context): void
    {
        if (!PrintableName::valid($this->name)) {
            $context->buildViolation('Use a printable name of 1–128 characters.')->atPath('name')->addViolation();
        }
        foreach (['maxRequests' => [1, 10000], 'requestsPerMinute' => [1, 60], 'expiresInDays' => [1, 365]] as $field => [$min, $max]) {
            if ($field === 'expiresInDays' && $this->$field === '') { continue; }
            if (!preg_match('/\A[0-9]{1,5}\z/D', $this->$field) || (int) $this->$field < $min || (int) $this->$field > $max) {
                $context->buildViolation("Use an integer from $min to $max.")->atPath($field)->addViolation();
            }
        }
        try { self::microUsd($this->budgetUsd); }
        catch (\InvalidArgumentException) { $context->buildViolation('Use USD 0–100, with at most six decimal places.')->atPath('budgetUsd')->addViolation(); }
    }

    public static function microUsd(string $value): int
    {
        if (!preg_match('/\A([0-9]{1,3})(?:\.([0-9]{1,6}))?\z/D', $value, $parts)) { throw new \InvalidArgumentException('Invalid USD threshold.'); }
        $amount = (int) $parts[1] * 1000000 + (int) str_pad($parts[2] ?? '', 6, '0');
        if ($amount > 100000000) { throw new \InvalidArgumentException('Invalid USD threshold.'); }
        return $amount;
    }
}
