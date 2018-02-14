<?php

namespace luka8088\ci;

class SymbolFinder {

  protected $data = [];

  function findByLocation ($file, $line, $column) {
    if (!isset($this->data[$file . ":maxColumn"])) {
      if (is_file($file)) {
        static $nameTokens = [T_WHITESPACE, T_STRING, T_NS_SEPARATOR];
        $contextStack = [
          ["name" => "", "entered" => false, "isClass" => false],
          ["name" => "", "entered" => false, "isClass" => false],
        ];
        $beginIndex = 0;
        $maxColumn = 1;
        $tokens = token_get_all(file_get_contents($file));
        $index = 0;
        $nextToken = function () use (&$tokens, &$index, &$maxColumn) {
          $index += 1;
          if ($index >= count($tokens))
            return;
          if (!is_array($tokens[$index]))
            $tokens[$index] = [
              0,
              $tokens[$index],
              $tokens[$index - 1][2] + count(explode("\n", $tokens[$index - 1][1])) - 1,
            ];
          $tokens[$index][3] = isset($tokens[$index - 1])
            ? (
              strpos($tokens[$index - 1][1], "\n") !== false
                ? 1 + strlen(substr($tokens[$index - 1][1], strrpos($tokens[$index - 1][1], "\n") + 1))
                : $tokens[$index - 1][3] + strlen($tokens[$index - 1][1])
              )
            : 1
          ;
          $maxColumn = max($maxColumn, $tokens[$index][3]);
        };
        while ($index < count($tokens)) {
          if ($tokens[$index][0] == T_NAMESPACE) {
            if ($beginIndex == 0)
              $beginIndex = $index;
            $name = "";
            $nextToken();
            while (isset($tokens[$index]) && in_array($tokens[$index][0], $nameTokens)) {
              $name .= trim($tokens[$index][1]);
              $nextToken();
            }
            array_unshift($contextStack, ["name" => $name, "entered" => false, "isClass" => false]);
            if ($contextStack[0]["name"] != $contextStack[1]["name"])
              $this->data[$file . ":" . $tokens[$beginIndex][2] . ":" . $tokens[$beginIndex][3]]
                = $contextStack[0]["name"];
            $beginIndex = 0;
            continue;
          }
          if ($tokens[$index][0] == T_CLASS) {
            if ($beginIndex == 0)
              $beginIndex = $index;
            $name = "";
            $nextToken();
            while (isset($tokens[$index]) && in_array($tokens[$index][0], $nameTokens)) {
              $name .= trim($tokens[$index][1]);
              $nextToken();
            }
            array_unshift($contextStack, [
              "name" => trim($contextStack[0]["name"] . "\\" . $name, "\\"),
              "entered" => false,
              "isClass" => true,
            ]);
            if ($contextStack[0]["name"] != $contextStack[1]["name"])
              $this->data[$file . ":" . $tokens[$beginIndex][2] . ":" . $tokens[$beginIndex][3]]
                = $contextStack[0]["name"];
            $beginIndex = 0;
            continue;
          }
          if ($tokens[$index][0] == T_FUNCTION) {
            if ($beginIndex == 0)
              $beginIndex = $index;
            $name = "";
            $nextToken();
            while (isset($tokens[$index]) && in_array($tokens[$index][0], $nameTokens)) {
              $name .= trim($tokens[$index][1]);
              $nextToken();
            }
            array_unshift($contextStack, [
              "name" => trim($contextStack[0]["name"] . ($contextStack[0]["isClass"] ? "::" : "\\") . $name, "\\:"),
              "entered" => false,
              "isClass" => false,
            ]);
            if ($contextStack[0]["name"] != $contextStack[1]["name"])
              $this->data[$file . ":" . $tokens[$beginIndex][2] . ":" . $tokens[$beginIndex][3]]
                = $contextStack[0]["name"];
            $beginIndex = 0;
            continue;
          }
          if (in_array($tokens[$index][0], [T_VARIABLE, T_USE]))
            $beginIndex = 0;
          if (in_array($tokens[$index][0], [T_PUBLIC, T_PROTECTED, T_PRIVATE, T_ABSTRACT, T_DOC_COMMENT]))
            if ($beginIndex == 0)
              $beginIndex = $index;
          if ($tokens[$index][1] == "{") {
            if (!$contextStack[0]["entered"])
              $contextStack[0]["entered"] = true;
            else
              array_unshift($contextStack, [
                "name" => $contextStack[0]["name"],
                "entered" => true,
                "isClass" => false,
              ]);
            $nextToken();
            continue;
          }
          if ($tokens[$index][1] == "}" && count($contextStack) > 2) {
            if ($contextStack[0]["name"] != $contextStack[1]["name"])
              $this->data[
                $file
                . ":" . ($tokens[$index][2] + count(explode("\n", $tokens[$index][1])) - 1)
                . ":" . ($tokens[$index][3] + strlen($tokens[$index][1]))
              ] = $contextStack[1]["name"];
            array_shift($contextStack);
            $nextToken();
            continue;
          }
          $nextToken();
        }
        while (count($contextStack) > 2) {
          $this->data[
            $file
            . ":" . (end($tokens)[2] + count(explode("\n", end($tokens)[1])) - 1)
            . ":" . (end($tokens)[3] + strlen(end($tokens)[1]))
          ] = $contextStack[0]["name"];
          array_shift($contextStack);
        }
        $this->data[$file . ":maxColumn"] = $maxColumn;
      }
    }
    for ($scanLine = $line; $scanLine > 0; $scanLine -= 1)
      for ($scanColumn = $this->data[$file . ":maxColumn"]; $scanColumn > 0; $scanColumn -= 1)
        if (isset($this->data[$file . ":" . $scanLine . ":" . $scanColumn]))
          return $this->data[$file . ":" . $scanLine . ":" . $scanColumn];
    return $file;
  }
}
