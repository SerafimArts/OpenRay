<?php

declare(strict_types=1);

namespace Serafim\OpenRay;

use Symfony\Component\VarDumper\Cloner\Data;
use Symfony\Component\VarDumper\Dumper\HtmlDumper;

final class OpenRayHtmlDumper extends HtmlDumper
{
    /**
     * @var non-empty-string
     */
    private const string SF_DUMP_PCRE = '#<script>\h*Sfdump\("(.+?)".+?\)\h*</script>#isum';

    public function __construct($output = null, ?string $charset = null, int $flags = 0)
    {
        parent::__construct($output, $charset, $flags);
    }

    /**
     * @param array{
     *      maxDepth?: int<1, max>,
     *      maxStringLength?: int<1, max>,
     *      fileLinkFormat?: string|null,
     *     ...<string, mixed>
     * } $options
     * @return array{string|null, string}
     */
    public function dumpWithInfo(Data $data, array $options = []): array
    {
        $html = $this->dump($data, true, $options);

        $id = null;

        $html = \preg_replace_callback(self::SF_DUMP_PCRE, function (array $matches) use (&$id): string {
            $id = $matches[1];
            return '';
        }, $html);

        return [$id, $html];
    }

    #[\Override]
    public function getDumpHeader(): string
    {
        $this->headerIsDumped = true;

        return '';
    }
}
