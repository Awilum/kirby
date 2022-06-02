<?php

use Kirby\Toolkit\I18n;

return [
    'props' => [
        /**
         * Columns config for `layout: table`
         */
        'columns' => function (array $columns = null) {
            return $columns ?? [];
        },
        /**
         * Section layout.
         * Available layout methods: `list`, `cardlets`, `cards`, `table`.
         */
        'layout' => function (string $layout = 'list') {
            $layouts = ['list', 'cardlets', 'cards', 'table'];
            return in_array($layout, $layouts) ? $layout : 'list';
        },
        /**
         * The size option controls the size of cards. By default cards are auto-sized and the cards grid will always fill the full width. With a size you can disable auto-sizing. Available sizes: `tiny`, `small`, `medium`, `large`, `huge`
         */
        'size' => function (string $size = 'auto') {
            return $size;
        },
    ],
    'computed' => [
        'columns' => function () {
            $columns = [];

            if ($this->image !== false) {
                $columns['image'] = [
                    'label' => ' ',
                    'type'  => 'image',
                    'width' => 'var(--table-row-height)'
                ];
            }

            $columns['title'] = [
                'label' => I18n::translate('title'),
                'type'  => 'url'
            ];

            if ($this->info) {
                $columns['info'] = [
                    'label' => 'Info',
                    'type'  => 'text',
                ];
            }

            foreach ($this->columns as $columnName => $column) {
                $column['id'] = $columnName;
                $columns[$columnName . 'Cell'] = $column;
            }

            if ($this->type === 'pages') {
                $columns['flag'] = [
                    'label' => ' ',
                    'type'  => 'flag',
                    'width' => 'var(--table-row-height)'
                ];
            }

            return $columns;
        },
    ],
    'methods' => [
        'columnsValues' => function ($item, $model) {
            $item['title'] = [
                'text' => $model->toSafeString($this->text),
                'href' => $model->panel()->url(true)
            ];

            foreach ($this->columns as $columnName => $column) {
                // don't overwrite essential columns
                if (isset($item[$columnName]) === true) {
                    continue;
                }

                if (empty($column['value']) === false) {
                    $value = $model->toSafeString($column['value']);
                } else {
                    $value = $model->content()->get($column['id'] ?? $columnName)->value();
                }

                $item[$columnName] = $value;
            }

            return $item;
        }
    ],
];
