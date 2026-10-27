<?php

declare(strict_types=1);

namespace Lemonade\Cms\Content;

use InvalidArgumentException;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionProperty;

/**
 * Prevede explicitne mapovana domenova data na hodnoty relacnich sloupcu
 *
 * @template T of object
 */
final readonly class CmsDomainMapping
{
    /**
     * Nacte mapovani jedine explicitne predane domenove tridy
     *
     * @param class-string<T> $dataClass
     */
    public function __construct(private string $dataClass) {}

    /**
     * Vrati normalizovane hodnoty korenovych a lokalizovanych sloupcu
     *
     * @param T $data
     * @return array{root:array<string,int|string|null>,localized:array<string,int|string|null>}
     */
    public function values(object $data): array
    {
        if (!$data instanceof $this->dataClass) {
            throw new InvalidArgumentException('CMS domain data has an unexpected class.');
        }

        $values = ['root' => [], 'localized' => []];
        foreach ((new ReflectionClass($this->dataClass))->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
            $root = $property->getAttributes(CmsRoot::class);
            $localized = $property->getAttributes(CmsLocalized::class);
            if (count($root) + count($localized) !== 1) {
                throw new InvalidArgumentException('Each CMS domain property must have exactly one column mapping.');
            }
            $scope = $root !== [] ? 'root' : 'localized';
            $mapping = ($root[0] ?? $localized[0])->newInstance();
            $type = $property->getType();
            if (!$type instanceof ReflectionNamedType || !in_array($type->getName(), ['string', 'int', 'bool'], true)) {
                throw new InvalidArgumentException('CMS domain properties must use supported scalar types.');
            }
            $reserved = $scope === 'root'
                ? ['id', 'created_by', 'updated_by', 'deleted_by', 'owner_id', 'deleted_at', 'created_at', 'updated_at']
                : ['id', 'locale', 'title', 'page_title', 'summary', 'content', 'meta_description', 'publication_state', 'published_at', 'created_at', 'updated_at'];
            if (preg_match('/^[a-z][a-z0-9_]*$/D', $mapping->column) !== 1
                || in_array($mapping->column, $reserved, true)
                || array_key_exists($mapping->column, $values[$scope])
            ) {
                throw new InvalidArgumentException('CMS domain column mapping is invalid or duplicated.');
            }
            $values[$scope][$mapping->column] = $this->normalize(
                $property->getValue($data),
                $type->getName(),
                $mapping->decimalScale,
            );
        }

        return $values;
    }

    /**
     * Porovna mapovane domenove hodnoty s nactenymi databazovymi radky
     *
     * @param T $data
     * @param array<string,mixed> $root
     * @param array<string,mixed>|null $localized
     * @return array{root:bool,localized:bool}
     */
    public function changes(object $data, array $root, ?array $localized): array
    {
        $values = $this->values($data);
        $changes = ['root' => false, 'localized' => false];
        foreach ((new ReflectionClass($this->dataClass))->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
            $rootAttribute = $property->getAttributes(CmsRoot::class);
            $scope = $rootAttribute !== [] ? 'root' : 'localized';
            $mapping = ($rootAttribute[0] ?? $property->getAttributes(CmsLocalized::class)[0])->newInstance();
            $row = $scope === 'root' ? $root : $localized;
            if ($row === null) {
                $changes[$scope] = true;
                continue;
            }
            if (!array_key_exists($mapping->column, $row)) {
                throw new InvalidArgumentException('CMS domain column is missing from the database row.');
            }
            $type = $property->getType();
            if (!$type instanceof ReflectionNamedType) {
                throw new InvalidArgumentException('CMS domain property type is invalid.');
            }
            $stored = $this->normalize($row[$mapping->column], $type->getName(), $mapping->decimalScale);
            if ($values[$scope][$mapping->column] !== $stored) {
                $changes[$scope] = true;
            }
        }

        return $changes;
    }

    /**
     * Sjednoti databazovou a PHP reprezentaci bez pouziti float
     */
    private function normalize(mixed $value, string $type, ?int $decimalScale): int|string|null
    {
        if ($value === null) {
            return null;
        }
        if ($decimalScale !== null) {
            if ($type !== 'string' || $decimalScale < 0 || !is_string($value)
                || preg_match('/^(-?)([0-9]+)(?:\.([0-9]+))?$/D', $value, $matches) !== 1
                || strlen($matches[3] ?? '') > $decimalScale
            ) {
                throw new InvalidArgumentException('CMS decimal value is invalid.');
            }
            $whole = ltrim($matches[2], '0');
            $whole = $whole === '' ? '0' : $whole;
            $fraction = str_pad($matches[3] ?? '', $decimalScale, '0');
            $sign = $matches[1] === '-' && ($whole !== '0' || trim($fraction, '0') !== '') ? '-' : '';

            return $sign . $whole . ($decimalScale === 0 ? '' : '.' . $fraction);
        }
        if ($type === 'bool') {
            return (int) (bool) $value;
        }
        if ($type === 'int') {
            return (int) $value;
        }

        return (string) $value;
    }
}
