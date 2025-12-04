/**
 * LinkPilot Editor Sidebar
 * 
 * Provides a Gutenberg sidebar panel for analyzing content
 * and suggesting internal links.
 */

import { registerPlugin } from '@wordpress/plugins';
import { PluginSidebar, PluginSidebarMoreMenuItem } from '@wordpress/edit-post';
import { PanelBody, Button, Spinner, ExternalLink } from '@wordpress/components';
import { useSelect } from '@wordpress/data';
import { useState, useCallback } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import { link as linkIcon } from '@wordpress/icons';

/**
 * Main LinkPilot Sidebar Component
 */
const LinkPilotSidebar = () => {
    const [isAnalyzing, setIsAnalyzing] = useState(false);
    const [keywords, setKeywords] = useState(null);
    const [suggestions, setSuggestions] = useState(null);
    const [error, setError] = useState(null);

    // Get current post data
    const { postId, postContent } = useSelect((select) => {
        const editor = select('core/editor');
        return {
            postId: editor.getCurrentPostId(),
            postContent: editor.getEditedPostContent(),
        };
    }, []);

    /**
     * Analyze the current post content
     */
    const analyzeContent = useCallback(async () => {
        setIsAnalyzing(true);
        setError(null);
        setKeywords(null);
        setSuggestions(null);

        try {
            const response = await apiFetch({
                path: '/linkpilot/v1/analyze',
                method: 'POST',
                data: {
                    post_id: postId,
                    content: postContent,
                },
            });

            if (response.success) {
                setKeywords(response.keywords);
                setSuggestions(response.suggestions);
            } else {
                setError(__('Failed to analyze content.', 'linkpilot'));
            }
        } catch (err) {
            setError(err.message || __('An error occurred while analyzing content.', 'linkpilot'));
        } finally {
            setIsAnalyzing(false);
        }
    }, [postId, postContent]);

    /**
     * Copy link to clipboard
     */
    const copyToClipboard = useCallback((url, title) => {
        const linkHtml = `<a href="${url}">${title}</a>`;
        
        // Try to copy as HTML
        if (navigator.clipboard && navigator.clipboard.write) {
            const blob = new Blob([linkHtml], { type: 'text/html' });
            const clipboardItem = new ClipboardItem({ 'text/html': blob });
            navigator.clipboard.write([clipboardItem]).then(() => {
                showToast(__('Link copied to clipboard!', 'linkpilot'));
            }).catch(() => {
                // Fallback to plain text
                navigator.clipboard.writeText(url).then(() => {
                    showToast(__('URL copied to clipboard!', 'linkpilot'));
                });
            });
        } else {
            // Fallback for older browsers
            const textArea = document.createElement('textarea');
            textArea.value = url;
            document.body.appendChild(textArea);
            textArea.select();
            document.execCommand('copy');
            document.body.removeChild(textArea);
            showToast(__('URL copied to clipboard!', 'linkpilot'));
        }
    }, []);

    /**
     * Show a toast notification
     */
    const showToast = (message) => {
        const toast = document.createElement('div');
        toast.className = 'linkpilot-copied-toast';
        toast.textContent = message;
        document.body.appendChild(toast);
        
        setTimeout(() => {
            toast.remove();
        }, 2000);
    };

    /**
     * Render keyword tags
     */
    const renderKeywords = () => {
        if (!keywords) return null;

        const allKeywords = [];
        
        // Add single keywords
        if (keywords.single_keywords) {
            Object.entries(keywords.single_keywords).slice(0, 10).forEach(([word, count]) => {
                allKeywords.push({ term: word, count });
            });
        }
        
        // Add phrases
        if (keywords.phrases) {
            Object.entries(keywords.phrases).slice(0, 5).forEach(([phrase, count]) => {
                allKeywords.push({ term: phrase, count, isPhrase: true });
            });
        }

        if (allKeywords.length === 0) return null;

        return (
            <div className="linkpilot-keywords-section">
                <h4>{__('Extracted Keywords', 'linkpilot')}</h4>
                <div className="linkpilot-keywords-list">
                    {allKeywords.map((keyword, index) => (
                        <span key={index} className="linkpilot-keyword-tag">
                            {keyword.term}
                            <span className="keyword-count">{keyword.count}</span>
                        </span>
                    ))}
                </div>
            </div>
        );
    };

    /**
     * Render link suggestions
     */
    const renderSuggestions = () => {
        if (!suggestions) return null;

        if (suggestions.length === 0) {
            return (
                <div className="linkpilot-no-suggestions">
                    <span className="dashicons dashicons-info-outline"></span>
                    <p>{__('No matching internal links found. Try adding more content or using different keywords.', 'linkpilot')}</p>
                </div>
            );
        }

        return (
            <div className="linkpilot-suggestions-section">
                <h4>{__('Suggested Internal Links', 'linkpilot')}</h4>
                {suggestions.map((suggestion, index) => (
                    <div key={suggestion.id} className="linkpilot-suggestion-card">
                        <h5 className="linkpilot-suggestion-title">
                            {suggestion.title}
                        </h5>
                        <div className="linkpilot-suggestion-meta">
                            <span className="linkpilot-suggestion-type">
                                {suggestion.post_type}
                            </span>
                            <span className="linkpilot-suggestion-score">
                                <span className="dashicons dashicons-star-filled"></span>
                                {__('Relevance:', 'linkpilot')} {suggestion.score}
                            </span>
                        </div>
                        {suggestion.matching_terms && suggestion.matching_terms.length > 0 && (
                            <div className="linkpilot-suggestion-terms">
                                <strong>{__('Matching:', 'linkpilot')}</strong> {suggestion.matching_terms.join(', ')}
                            </div>
                        )}
                        <div className="linkpilot-suggestion-actions">
                            <Button
                                variant="secondary"
                                size="small"
                                onClick={() => copyToClipboard(suggestion.url, suggestion.title)}
                            >
                                {__('Copy Link', 'linkpilot')}
                            </Button>
                            <ExternalLink
                                href={suggestion.url}
                                className="components-button is-secondary is-small"
                            >
                                {__('View', 'linkpilot')}
                            </ExternalLink>
                        </div>
                    </div>
                ))}
            </div>
        );
    };

    return (
        <>
            <PluginSidebarMoreMenuItem
                target="linkpilot-sidebar"
                icon={linkIcon}
            >
                {__('LinkPilot', 'linkpilot')}
            </PluginSidebarMoreMenuItem>
            
            <PluginSidebar
                name="linkpilot-sidebar"
                title={__('LinkPilot', 'linkpilot')}
                icon={linkIcon}
            >
                <div className="linkpilot-panel">
                    <PanelBody
                        title={__('Content Analyzer', 'linkpilot')}
                        initialOpen={true}
                    >
                        <div className="linkpilot-analyze-section">
                            <p>
                                {__('Analyze your content to find relevant internal linking opportunities.', 'linkpilot')}
                            </p>
                            <Button
                                variant="primary"
                                onClick={analyzeContent}
                                disabled={isAnalyzing}
                                className="linkpilot-analyze-btn"
                            >
                                {isAnalyzing ? (
                                    <>
                                        <Spinner />
                                        {__('Analyzing...', 'linkpilot')}
                                    </>
                                ) : (
                                    __('Analyze Content', 'linkpilot')
                                )}
                            </Button>
                        </div>
                    </PanelBody>

                    {isAnalyzing && (
                        <div className="linkpilot-loading">
                            <Spinner />
                            {__('Scanning content and finding suggestions...', 'linkpilot')}
                        </div>
                    )}

                    {error && (
                        <div className="linkpilot-error">
                            <span className="dashicons dashicons-warning"></span>
                            <p>{error}</p>
                        </div>
                    )}

                    {!isAnalyzing && !error && keywords && (
                        <>
                            {renderKeywords()}
                            {renderSuggestions()}
                        </>
                    )}
                </div>
            </PluginSidebar>
        </>
    );
};

// Register the plugin
registerPlugin('linkpilot', {
    render: LinkPilotSidebar,
});
