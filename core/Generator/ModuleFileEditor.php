<?php

namespace Ryra\Core\Generator;

use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\ArrayItem;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\Return_;
use PhpParser\Node\Stmt\Use_;
use PhpParser\Node\Stmt\UseUse;
use PhpParser\NodeFinder;
use PhpParser\ParserFactory;
use PhpParser\PrettyPrinter\Standard;

class ModuleFileEditor
{
    /**
     * Thêm class vào section (controllers/providers) trong file .module.php,
     * sau đó reformat toàn bộ file bằng pretty printer.
     * Trả về true nếu file được cập nhật, false nếu đã tồn tại hoặc lỗi.
     */
    public function addEntry(string $moduleFile, string $section, string $fqcn): bool
    {
        if (!file_exists($moduleFile)) {
            return false;
        }

        $content   = file_get_contents($moduleFile);
        $shortName = substr($fqcn, strrpos($fqcn, '\\') + 1);

        if (str_contains($content, $shortName . '::class')) {
            return false;
        }

        $stmts = (new ParserFactory)->createForNewestSupportedVersion()->parse($content);

        if ($stmts === null) {
            return false;
        }

        if (!str_contains($content, "use {$fqcn};")) {
            $this->insertUseStatement($stmts, new Use_([new UseUse(new Name(ltrim($fqcn, '\\')))]));
        }

        if (!$this->insertIntoSection($stmts, $section, $shortName)) {
            return false;
        }

        $printer = new class extends Standard {
            // Tất cả array literal không rỗng đều in multiline + trailing comma.
            protected function pMaybeMultiline(array $nodes, bool $trailingComma = false): string
            {
                if ($trailingComma && !empty($nodes)) {
                    return $this->pCommaSeparatedMultiline($nodes, $trailingComma) . $this->nl;
                }
                return parent::pMaybeMultiline($nodes, $trailingComma);
            }
        };

        file_put_contents($moduleFile, $this->postFormat($printer->prettyPrintFile($stmts)));
        return true;
    }

    // -------------------------------------------------------------------------

    private function postFormat(string $code): string
    {
        // Blank line giữa block use và return
        $code = preg_replace('/(use [^;]+;)\n(return )/', "$1\n\n$2", $code);

        // Blank line giữa các item của outer return array (nhận ra bằng 4-space indent + string key)
        $code = preg_replace('/,\n(    \')/', ",\n\n$1", $code);

        return $code;
    }

    private function insertUseStatement(array &$stmts, Use_ $useNode): void
    {
        $lastUseIdx = -1;
        foreach ($stmts as $i => $stmt) {
            if ($stmt instanceof Use_) {
                $lastUseIdx = $i;
            }
        }

        array_splice($stmts, $lastUseIdx + 1, 0, [$useNode]);
    }

    private function insertIntoSection(array &$stmts, string $section, string $shortName): bool
    {
        $finder     = new NodeFinder();
        $returnNode = $finder->findFirstInstanceOf($stmts, Return_::class);

        if (!$returnNode || !$returnNode->expr instanceof Array_) {
            return false;
        }

        $entry       = new ClassConstFetch(new Name($shortName), new Identifier('class'));
        $returnArray = $returnNode->expr;

        foreach ($returnArray->items as $item) {
            if ($item instanceof ArrayItem
                && $item->key instanceof String_
                && $item->key->value === $section
                && $item->value instanceof Array_
            ) {
                $item->value->items[] = new ArrayItem($entry);
                return true;
            }
        }

        // Section chưa tồn tại → tạo mới ở cuối return array
        $returnArray->items[] = new ArrayItem(
            new Array_([new ArrayItem($entry)], ['kind' => Array_::KIND_SHORT]),
            new String_($section)
        );

        return true;
    }
}
