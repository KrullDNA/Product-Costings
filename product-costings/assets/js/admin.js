(function ($) {
    'use strict';

    var PC = {
        nextIndex: 0,

        init: function () {
            this.cacheElements();
            this.nextIndex = this.$body.find('.pc-row').length;
            this.initSortable();
            this.initTradeNameSelects();
            this.bindEvents();
            this.recalcTo100();
            this.recalcCostSummary();
        },

        cacheElements: function () {
            this.$wrap  = $('#pc-formula-wrap');
            this.$body  = $('#pc-formula-body');
            this.$total = $('#pc-total-ww');
        },

        /* ──────────────────────────────
         * Sortable (drag & drop)
         * ────────────────────────────── */
        initSortable: function () {
            var self = this;
            this.$body.sortable({
                handle: '.pc-drag-handle',
                axis: 'y',
                opacity: 0.65,
                placeholder: 'pc-sortable-placeholder',
                update: function () {
                    self.reindexRows();
                    self.recalcTo100();
                }
            });
        },

        /* ──────────────────────────────
         * Trade Name select2-style search
         * ────────────────────────────── */
        initTradeNameSelects: function () {
            this.$body.find('.pc-field-trade-name').each(function () {
                PC.initSingleTradeSelect($(this));
            });
        },

        initSingleTradeSelect: function ($select) {
            // Only init once.
            if ($select.data('pc-init')) return;
            $select.data('pc-init', true);

            var $row = $select.closest('.pc-row');

            // Build a simple autocomplete wrapper.
            var $wrapper = $('<div class="pc-trade-search-wrap"></div>');
            var $input   = $('<input type="text" class="pc-trade-search" placeholder="Search trade names…">');
            var $list    = $('<ul class="pc-trade-results"></ul>');
            $wrapper.append($input).append($list);
            $select.after($wrapper);
            $select.hide();

            // Show selected value.
            if ($select.val()) {
                $input.val($select.find('option:selected').text());
            }

            var searchTimer;
            $input.on('input', function () {
                clearTimeout(searchTimer);
                var q = $(this).val();
                if (q.length < 2) {
                    $list.empty().hide();
                    return;
                }
                searchTimer = setTimeout(function () {
                    $.ajax({
                        url: pcData.ajaxUrl,
                        data: { action: 'pc_search_trade_names', nonce: pcData.nonce, q: q },
                        success: function (res) {
                            $list.empty();
                            if (res.success && res.data.length) {
                                $.each(res.data, function (_, item) {
                                    $list.append(
                                        $('<li></li>').text(item.text).data('id', item.id)
                                    );
                                });
                                $list.show();
                            } else {
                                $list.append('<li class="pc-no-results">No results</li>').show();
                            }
                        }
                    });
                }, 300);
            });

            // Select a result.
            $list.on('click', 'li:not(.pc-no-results)', function () {
                var id   = $(this).data('id');
                var text = $(this).text();
                $select.html('<option value="' + id + '" selected>' + $('<span>').text(text).html() + '</option>');
                $input.val(text);
                $list.empty().hide();

                // Fetch meta.
                PC.fetchTradeMeta(id, $row);
            });

            // Hide results on blur.
            $input.on('blur', function () {
                setTimeout(function () { $list.empty().hide(); }, 200);
            });

            // Clear button.
            $input.on('keydown', function (e) {
                if (e.key === 'Escape') {
                    $select.val('').html('<option value="">— Select —</option>');
                    $input.val('');
                    $row.find('.pc-field-ph').val('');
                    $row.find('.pc-field-price').val('');
                    $row.find('.pc-field-moq').val('');
                    $list.empty().hide();
                }
            });
        },

        fetchTradeMeta: function (postId, $row) {
            $.ajax({
                url: pcData.ajaxUrl,
                data: { action: 'pc_get_trade_name_meta', nonce: pcData.nonce, post_id: postId },
                success: function (res) {
                    if (res.success) {
                        $row.find('.pc-field-ph').val(res.data.ph_range || '');
                        $row.find('.pc-field-price').val(res.data.price_per_kg || '');
                        $row.find('.pc-field-moq').val(res.data.moq || '');

                        // Optionally pre-select function if available.
                        if (res.data.function1) {
                            $row.find('.pc-field-function').val(res.data.function1);
                        }

                        PC.recalcCostSummary();
                    }
                }
            });
        },

        /* ──────────────────────────────
         * Events
         * ────────────────────────────── */
        bindEvents: function () {
            var self = this;

            // Add row.
            $('#pc-add-row').on('click', function () {
                self.addRow();
            });

            // Remove row.
            this.$wrap.on('click', '.pc-remove-row', function () {
                $(this).closest('.pc-row').remove();
                self.reindexRows();
                self.recalcTo100();
                self.recalcCostSummary();
            });

            // Duplicate row.
            this.$wrap.on('click', '.pc-duplicate-row', function () {
                var $row  = $(this).closest('.pc-row');
                var $clone = $row.clone();
                // Update index.
                var newIdx = self.nextIndex++;
                $clone.attr('data-index', newIdx);
                $clone.find('[name]').each(function () {
                    var name = $(this).attr('name');
                    $(this).attr('name', name.replace(/pc_rows\[\d+\]/, 'pc_rows[' + newIdx + ']'));
                });
                // Re-init trade name search on clone.
                $clone.find('.pc-trade-search-wrap').remove();
                $clone.find('.pc-field-trade-name').show().data('pc-init', false);
                $row.after($clone);
                self.initSingleTradeSelect($clone.find('.pc-field-trade-name'));
                self.recalcTo100();
                self.recalcCostSummary();
            });

            // Mark as "to 100%" row.
            this.$wrap.on('click', '.pc-mark-to100', function () {
                // Remove existing to100 markers.
                self.$body.find('.pc-row-to100').each(function () {
                    $(this).removeClass('pc-row-to100');
                    var $ww = $(this).find('.pc-field-ww');
                    $ww.removeAttr('readonly');
                    $(this).find('.pc-to100-badge').remove();
                    $(this).find('input[name$="[is_to_100]"]').remove();
                    // Restore buttons.
                    var idx = $(this).data('index');
                    $(this).find('.pc-unmark-to100').replaceWith(
                        '<button type="button" class="button pc-mark-to100" title="Set as to 100% row">&#x1F4A7;</button>'
                    );
                });

                var $row = $(this).closest('.pc-row');
                $row.addClass('pc-row-to100');
                var $ww = $row.find('.pc-field-ww');
                $ww.attr('readonly', true);
                $ww.after('<input type="hidden" name="pc_rows[' + $row.data('index') + '][is_to_100]" value="1"><span class="pc-to100-badge">to 100%</span>');

                $(this).replaceWith(
                    '<button type="button" class="button pc-unmark-to100" title="Remove to 100%">&#x2716;</button>'
                );

                self.recalcTo100();
            });

            // Unmark "to 100%".
            this.$wrap.on('click', '.pc-unmark-to100', function () {
                var $row = $(this).closest('.pc-row');
                $row.removeClass('pc-row-to100');
                $row.find('.pc-field-ww').removeAttr('readonly');
                $row.find('.pc-to100-badge').remove();
                $row.find('input[name$="[is_to_100]"]').remove();
                $(this).replaceWith(
                    '<button type="button" class="button pc-mark-to100" title="Set as to 100% row">&#x1F4A7;</button>'
                );
                self.recalcTo100();
            });

            // Recalc on %w/w change.
            this.$wrap.on('input change', '.pc-field-ww', function () {
                self.recalcTo100();
                self.recalcCostSummary();
            });

            // Recalc on costing fields change.
            $(document).on('input change', '.pc-costing-field', function () {
                self.recalcCostSummary();
            });
        },

        /* ──────────────────────────────
         * Add new row
         * ────────────────────────────── */
        addRow: function () {
            var template = wp.template('pc-row');
            var html = template({ i: this.nextIndex });
            this.$body.append(html);

            var $newRow = this.$body.find('.pc-row').last();
            this.initSingleTradeSelect($newRow.find('.pc-field-trade-name'));

            this.nextIndex++;
            this.reindexRows();
        },

        /* ──────────────────────────────
         * Reindex rows after sort / remove
         * ────────────────────────────── */
        reindexRows: function () {
            this.$body.find('.pc-row').each(function (idx) {
                $(this).attr('data-index', idx);
                $(this).find('[name]').each(function () {
                    var name = $(this).attr('name');
                    if (name) {
                        $(this).attr('name', name.replace(/pc_rows\[\d+\]/, 'pc_rows[' + idx + ']'));
                    }
                });
            });
            this.nextIndex = this.$body.find('.pc-row').length;
        },

        /* ──────────────────────────────
         * Recalc "to 100%" dynamically
         * ────────────────────────────── */
        recalcTo100: function () {
            var $to100Row = this.$body.find('.pc-row-to100');
            if (!$to100Row.length) {
                this.updateTotal();
                return;
            }

            var sum = 0;
            this.$body.find('.pc-row').not('.pc-row-to100').each(function () {
                var val = parseFloat($(this).find('.pc-field-ww').val()) || 0;
                sum += val;
            });

            var to100val = Math.max(0, 100 - sum);
            to100val = Math.round(to100val * 10000) / 10000;
            $to100Row.find('.pc-field-ww').val(to100val);

            this.updateTotal();
        },

        updateTotal: function () {
            var sum = 0;
            this.$body.find('.pc-row .pc-field-ww').each(function () {
                sum += parseFloat($(this).val()) || 0;
            });
            this.$total.html('<strong>' + sum.toFixed(2) + '</strong>');
        },

        /* ──────────────────────────────
         * Cost Summary Calculation
         * ────────────────────────────── */
        recalcCostSummary: function () {
            // Raw material cost per KG = sum of (percent_w_w / 100 * price_per_kg) for each row.
            var rawCostPerKg = 0;
            this.$body.find('.pc-row').each(function () {
                var ww    = parseFloat($(this).find('.pc-field-ww').val()) || 0;
                var price = parseFloat($(this).find('.pc-field-price').val()) || 0;
                rawCostPerKg += (ww / 100) * price;
            });

            var batchSize      = parseFloat($('#pc_batch_size').val()) || 0;
            var facilityCosts  = parseFloat($('#pc_facility_running_costs').val()) || 0;
            var labourMfg      = parseFloat($('#pc_labour_manufacturing').val()) || 0;
            var labourFill     = parseFloat($('#pc_labour_filling').val()) || 0;
            var packagingUnit  = parseFloat($('#pc_packaging_unit_cost').val()) || 0;
            var packagingSize  = parseFloat($('#pc_packaging_size').val()) || 0;

            var rawCostBatch = rawCostPerKg * batchSize;
            var unitsPerBatch = packagingSize > 0 ? Math.floor((batchSize * 1000) / packagingSize) : 0;

            var totalBatchCost = rawCostBatch + facilityCosts + labourMfg + labourFill + (packagingUnit * unitsPerBatch);
            var costPerUnit = unitsPerBatch > 0 ? totalBatchCost / unitsPerBatch : 0;

            $('#pc-raw-cost-kg').text('£' + rawCostPerKg.toFixed(4));
            $('#pc-raw-cost-batch').text(batchSize > 0 ? '£' + rawCostBatch.toFixed(2) : '—');
            $('#pc-units-batch').text(unitsPerBatch > 0 ? unitsPerBatch : '—');
            $('#pc-batch-cost').text(totalBatchCost > 0 ? '£' + totalBatchCost.toFixed(2) : '—');
            $('#pc-cost-unit').text(costPerUnit > 0 ? '£' + costPerUnit.toFixed(4) : '—');
        }
    };

    $(document).ready(function () {
        if ($('#pc-formula-wrap').length) {
            PC.init();
        }
    });

})(jQuery);
