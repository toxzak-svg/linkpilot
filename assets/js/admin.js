/**
 * LinkPilot Admin JavaScript
 */
(function($) {
    'use strict';

    var LinkPilotAdmin = {
        currentPage: 1,
        totalPages: 1,

        init: function() {
            this.bindEvents();
        },

        bindEvents: function() {
            $('#linkpilot-scan-btn').on('click', this.scanOrphanedContent.bind(this));
            $(document).on('click', '.linkpilot-pagination button', this.handlePagination.bind(this));
        },

        scanOrphanedContent: function(page) {
            var self = this;
            var pageNum = typeof page === 'number' ? page : 1;
            
            // Get selected post types
            var postTypes = $('#linkpilot-post-type').val();
            if (!postTypes || postTypes.length === 0) {
                postTypes = ['post', 'page'];
            }

            // Show loading
            $('#linkpilot-loading').show();
            $('#linkpilot-results').hide();
            $('#linkpilot-no-results').hide();
            $('#linkpilot-error').hide();

            // Make AJAX request
            $.ajax({
                url: linkpilotAdmin.restUrl + 'orphaned',
                method: 'GET',
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', linkpilotAdmin.nonce);
                },
                data: {
                    post_type: postTypes,
                    page: pageNum,
                    per_page: 50
                },
                success: function(response) {
                    $('#linkpilot-loading').hide();
                    
                    if (response.success) {
                        self.currentPage = response.current_page;
                        self.totalPages = response.total_pages;
                        
                        if (response.posts.length > 0) {
                            self.renderResults(response.posts, response.total);
                        } else {
                            $('#linkpilot-no-results').show();
                        }
                    } else {
                        self.showError('An error occurred while scanning content.');
                    }
                },
                error: function(xhr) {
                    $('#linkpilot-loading').hide();
                    var message = 'An error occurred while scanning content.';
                    if (xhr.responseJSON && xhr.responseJSON.message) {
                        message = xhr.responseJSON.message;
                    }
                    self.showError(message);
                }
            });
        },

        renderResults: function(posts, total) {
            var $tbody = $('#linkpilot-results-body');
            $tbody.empty();

            posts.forEach(function(post) {
                var row = '<tr>' +
                    '<td class="column-title">' +
                        '<strong><a href="' + self.escapeHtml(post.url) + '" target="_blank">' + self.escapeHtml(post.title) + '</a></strong>' +
                    '</td>' +
                    '<td class="column-type">' +
                        '<span class="linkpilot-post-type-badge post-type-' + self.escapeHtml(post.post_type) + '">' + self.escapeHtml(post.post_type) + '</span>' +
                    '</td>' +
                    '<td class="column-date">' + self.formatDate(post.date) + '</td>' +
                    '<td class="column-actions">' +
                        '<div class="row-actions">' +
                            '<a href="' + self.escapeHtml(post.edit_url) + '" class="button button-small">' +
                                '<span class="dashicons dashicons-edit"></span> Edit' +
                            '</a>' +
                            '<a href="' + self.escapeHtml(post.url) + '" target="_blank" class="button button-small">' +
                                '<span class="dashicons dashicons-external"></span> View' +
                            '</a>' +
                        '</div>' +
                    '</td>' +
                '</tr>';
                $tbody.append(row);
            });

            $('#linkpilot-total-count').text(total);
            $('#linkpilot-results').show();
            
            this.renderPagination();
        },

        renderPagination: function() {
            var $pagination = $('#linkpilot-pagination');
            $pagination.empty();

            if (this.totalPages <= 1) {
                return;
            }

            for (var i = 1; i <= this.totalPages; i++) {
                var $button = $('<button type="button" class="button">' + i + '</button>');
                $button.data('page', i);
                if (i === this.currentPage) {
                    $button.addClass('current');
                }
                $pagination.append($button);
            }
        },

        handlePagination: function(e) {
            var page = $(e.target).data('page');
            if (page) {
                this.scanOrphanedContent(page);
            }
        },

        formatDate: function(dateString) {
            var date = new Date(dateString);
            return date.toLocaleDateString();
        },

        escapeHtml: function(text) {
            var div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        },

        showError: function(message) {
            $('#linkpilot-error').show().find('p').text(message);
        }
    };

    // Use self reference for callbacks
    var self = LinkPilotAdmin;

    $(document).ready(function() {
        LinkPilotAdmin.init();
    });

})(jQuery);
