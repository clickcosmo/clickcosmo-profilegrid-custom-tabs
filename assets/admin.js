(function ($) {
    'use strict';

    function initEditor($textarea) {
        if (!window.wp || !wp.editor || !$textarea.length) {
            return;
        }

        var id = $textarea.attr('id');
        if (!id || $('#' + id + '-wrap').length) {
            return;
        }

        wp.editor.initialize(id, {
            tinymce: {
                wpautop: true,
                toolbar1: 'formatselect,bold,italic,bullist,numlist,blockquote,alignleft,aligncenter,alignright,link,unlink,undo,redo'
            },
            quicktags: true
        });
    }

    function syncEditors() {
        $('#ccpgt-tabs .ccpgt-editor').each(function () {
            if (window.tinymce) {
                var editor = tinymce.get(this.id);
                if (editor) {
                    editor.save();
                }
            }
        });
    }

    function reindex() {
        syncEditors();

        $('#ccpgt-tabs .ccpgt-tab-card').each(function (index) {
            $(this).attr('data-index', index);
            $(this).find('[name]').each(function () {
                this.name = this.name.replace(/ccpgt_tabs\[[^\]]+\]/, 'ccpgt_tabs[' + index + ']');
            });
        });
    }

    function filterSourceFields($card, clearInvalid) {
        var groupId = String($card.find('.ccpgt-source-group').val() || '');
        var $fields = $card.find('.ccpgt-source-fields');

        $fields.find('option').each(function () {
            var $option = $(this);
            var matches = groupId && String($option.data('group')) === groupId;

            $option.prop('disabled', !matches).prop('hidden', !matches);

            if (clearInvalid && !matches) {
                $option.prop('selected', false);
            }
        });
    }

    function updateContentSource($card) {
        var source = String($card.find('.ccpgt-content-source').val() || 'custom');
        var isFields = source === 'profilegrid_fields';

        $card.find('.ccpgt-pg-fields-row').toggle(isFields);
        $card.find('.ccpgt-custom-content-row').toggle(!isFields);

        if (isFields) {
            filterSourceFields($card, false);
        }
    }

    $(function () {
        var $tabs = $('#ccpgt-tabs');

        $tabs.find('.ccpgt-editor').each(function () {
            initEditor($(this));
        });

        $tabs.children('.ccpgt-tab-card').each(function () {
            updateContentSource($(this));
        });

        $('#ccpgt-add-tab').on('click', function (event) {
            event.preventDefault();
            var index = $tabs.children('.ccpgt-tab-card').length;
            var html = $('#tmpl-ccpgt-row').html().split('__INDEX__').join(index);
            $tabs.append(html);
            reindex();
            var $newCard = $tabs.children('.ccpgt-tab-card').last();
            initEditor($newCard.find('.ccpgt-editor'));
            updateContentSource($newCard);
        });

        function toggleCard($card) {
            var $body = $card.children('.ccpgt-card-body');
            var $header = $card.children('.ccpgt-card-header');
            var $button = $header.find('.ccpgt-toggle');
            var collapsed = $card.toggleClass('is-collapsed').hasClass('is-collapsed');

            if (collapsed) {
                $body.stop(true, true).slideUp(120, function () {
                    $body.css('display', 'none');
                });
            } else {
                $body.stop(true, true).slideDown(120, function () {
                    $body.css('display', 'block');
                });
            }
            $header.attr('aria-expanded', collapsed ? 'false' : 'true');
            $button.attr('aria-expanded', collapsed ? 'false' : 'true');
            $button.find('.dashicons')
                .toggleClass('dashicons-arrow-up', !collapsed)
                .toggleClass('dashicons-arrow-down', collapsed);
        }

        $tabs.on('click', '.ccpgt-card-header', function (event) {
            if ($(event.target).closest('.ccpgt-remove, .ccpgt-icon-picker, input, select, textarea, a').length) {
                return;
            }

            event.preventDefault();
            toggleCard($(this).closest('.ccpgt-tab-card'));
        });

        $tabs.on('keydown', '.ccpgt-card-header', function (event) {
            if (event.key !== 'Enter' && event.key !== ' ') {
                return;
            }

            if ($(event.target).closest('.ccpgt-remove, input, select, textarea, a').length) {
                return;
            }

            event.preventDefault();
            toggleCard($(this).closest('.ccpgt-tab-card'));
        });

        $tabs.on('click', '.ccpgt-remove', function (event) {
            event.preventDefault();
            event.stopPropagation();
            var $card = $(this).closest('.ccpgt-tab-card');
            var id = $card.find('.ccpgt-editor').attr('id');

            if (id && window.wp && wp.editor) {
                try {
                    wp.editor.remove(id);
                } catch (e) {}
            }

            $card.remove();
            reindex();
        });

        $tabs.on('input', '.ccpgt-title', function () {
            $(this).closest('.ccpgt-tab-card').find('.ccpgt-title-preview').text($(this).val() || 'New Tab');
        });

        $tabs.on('change', '.ccpgt-content-source', function () {
            updateContentSource($(this).closest('.ccpgt-tab-card'));
        });

        $tabs.on('change', '.ccpgt-source-group', function () {
            filterSourceFields($(this).closest('.ccpgt-tab-card'), true);
        });

        $tabs.on('click', '.ccpgt-clear-multiselect', function (event) {
            event.preventDefault();

            $(this)
                .siblings('select[multiple]')
                .find('option:selected')
                .prop('selected', false)
                .end()
                .trigger('change');
        });

        $tabs.on('click', '.ccpgt-icon-trigger', function (event) {
            event.preventDefault();
            event.stopPropagation();

            var $picker = $(this).siblings('.ccpgt-icon-picker');
            $('.ccpgt-icon-picker').not($picker).prop('hidden', true);
            $picker.prop('hidden', !$picker.prop('hidden'));

            if (!$picker.prop('hidden')) {
                $picker.find('.ccpgt-icon-search').trigger('focus');
            }
        });

        $tabs.on('input', '.ccpgt-icon-search', function () {
            var query = $.trim($(this).val().toLowerCase());
            $(this).closest('.ccpgt-icon-picker').find('.ccpgt-icon-option').each(function () {
                var haystack = String($(this).data('search') || '');
                $(this).toggle(!query || haystack.indexOf(query) !== -1);
            });
        });

        $tabs.on('click', '.ccpgt-icon-option', function (event) {
            event.preventDefault();

            var $option = $(this);
            var $field = $option.closest('.ccpgt-icon-field');
            var icon = String($option.data('icon') || '');

            $field.find('.ccpgt-icon-value').val(icon);
            $field.find('.ccpgt-icon-preview').html('<i class="' + icon + '" aria-hidden="true"></i>');
            $field.closest('.ccpgt-tab-card').find('.ccpgt-title-icon').html('<i class="' + icon + '" aria-hidden="true"></i>');
            $field.find('.ccpgt-icon-label').text('Change icon');
            $field.find('.ccpgt-icon-option').removeClass('is-selected');
            $option.addClass('is-selected');
            $field.find('.ccpgt-icon-picker').prop('hidden', true);
        });

        $tabs.on('click', '.ccpgt-icon-clear', function (event) {
            event.preventDefault();

            var $field = $(this).closest('.ccpgt-icon-field');
            $field.find('.ccpgt-icon-value').val('');
            $field.find('.ccpgt-icon-preview').html('<i class="fa fa-plus-square-o" aria-hidden="true"></i>');
            $field.closest('.ccpgt-tab-card').find('.ccpgt-title-icon').empty();
            $field.find('.ccpgt-icon-label').text('Choose icon');
            $field.find('.ccpgt-icon-option').removeClass('is-selected');
            $field.find('.ccpgt-icon-picker').prop('hidden', true);
        });

        $(document).on('click.ccpgtIcons', function (event) {
            if (!$(event.target).closest('.ccpgt-icon-field').length) {
                $('.ccpgt-icon-picker').prop('hidden', true);
            }
        });

        $('.ccpgt-wrap form').on('submit', function () {
            syncEditors();
        });
    });
}(jQuery));
