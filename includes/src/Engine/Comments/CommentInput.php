<?php
declare(strict_types=1);

namespace App\Engine\Comments;

use InvalidArgumentException;
use JsonException;
use stdClass;

final class CommentInput
{
    /** @return array<string,mixed> */
    public static function parse(string $contentType, string $body, array $form): array
    {
        $contentType = trim(explode(';', strtolower($contentType))[0]);
        if ($contentType === 'application/json' || str_ends_with($contentType, '+json')) {
            try {
                $decoded = json_decode($body, false, 512, JSON_THROW_ON_ERROR);
            } catch (JsonException $exception) {
                throw new InvalidArgumentException('Geçersiz JSON.', 0, $exception);
            }
            if (!$decoded instanceof stdClass) {
                throw new InvalidArgumentException('Yorum verisi bir JSON nesnesi olmalı.');
            }
            $input = get_object_vars($decoded);
        } elseif (in_array($contentType, ['', 'application/x-www-form-urlencoded', 'multipart/form-data'], true)) {
            $input = $form;
        } else {
            throw new InvalidArgumentException('Desteklenmeyen yorum veri biçimi.');
        }

        foreach ($input as $value) {
            if ((!is_scalar($value) && $value !== null) || (is_string($value) && !mb_check_encoding($value, 'UTF-8'))) {
                throw new InvalidArgumentException('Geçersiz yorum alanı.');
            }
        }
        foreach (['_token', 'action', 'body', 'reaction_type', 'reason', 'details'] as $field) {
            if (array_key_exists($field, $input) && !is_string($input[$field])) {
                throw new InvalidArgumentException('Geçersiz yorum alanı: ' . $field);
            }
        }
        foreach (['id', 'topic_id', 'comment_id', 'parent_id'] as $field) {
            if (!array_key_exists($field, $input)) {
                continue;
            }
            $id = (is_int($input[$field]) || is_string($input[$field]))
                ? filter_var($input[$field], FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]])
                : false;
            if ($id === false) {
                throw new InvalidArgumentException('Geçersiz yorum kimliği: ' . $field);
            }
            $input[$field] = $id;
        }
        $input['action'] ??= 'add';
        if (!in_array($input['action'], ['add', 'edit', 'delete', 'react', 'report'], true)) {
            throw new InvalidArgumentException('Geçersiz yorum işlemi.');
        }
        return $input;
    }

    public static function assertBodyLength(string $body, array $settings): void
    {
        $min = max(1, (int) ($settings['comment_min_length'] ?? 1));
        $max = max(1, (int) ($settings['max_comment_length'] ?? 2000));
        $length = mb_strlen($body, 'UTF-8');
        if ($length < $min) {
            throw new InvalidArgumentException("Yorum en az {$min} karakter olmalı.");
        }
        if ($length > $max) {
            throw new InvalidArgumentException("Yorum en fazla {$max} karakter olabilir.");
        }
    }
}
