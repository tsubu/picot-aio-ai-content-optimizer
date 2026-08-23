(function( $ ) {
    'use strict';
 //     console.log('Picot AIO Optimizer: admin.js loaded');

    // Ensure the localized object exists
    window.picot_aio_optimizer = window.picot_aio_optimizer || { strings: {} };
    var picot_aio_optimizer = window.picot_aio_optimizer;

    function isImageGenerationEnabled() {
        var value = picot_aio_optimizer.enable_image_gen;
        return value === true || value === 1 || value === '1';
    }

    /**
     * Centralized AJAX error handler
     * Logs detailed error to console and shows user-friendly message
     */
    function handleAjaxError(xhr, context) {
        var errorMsg = (picot_aio_optimizer.strings && picot_aio_optimizer.strings.error) ? picot_aio_optimizer.strings.error : 'An error occurred.';
        var detailedError = {
            context: context,
            status: xhr.status,
            statusText: xhr.statusText,
            responseText: xhr.responseText
        };
        
        // Log detailed error to console for debugging
 //         console.error('Picot AIO Optimizer AJAX Error:', detailedError);
        
        // Try to extract user-friendly message
        if (xhr.responseJSON && xhr.responseJSON.message) {
            errorMsg = xhr.responseJSON.message;
        } else if (xhr.status === 403) {
            errorMsg = picot_aio_optimizer.strings.permission_error || 'Permission denied. Please refresh the page and try again.';
        } else if (xhr.status === 500) {
            errorMsg = picot_aio_optimizer.strings.server_error || 'Server error. Please check the error log.';
        } else if (xhr.status === 0) {
            errorMsg = picot_aio_optimizer.strings.network_error || 'Network error. Please check your connection.';
        }
        
        return errorMsg;
    }

    /**
     * Centralized logging helper
     * Only logs in debug mode (checks for WP_DEBUG via localized script)
     */
    function debugLog(message, level) {
        // level = level || 'log';
        // var prefix = 'Picot AIO Optimizer: ';
        // if (level === 'error' || (window.picot_aio_optimizer && picot_aio_optimizer.debug_mode)) {
        //     console[level](prefix + message);
        // }
    }

    /**
     * Returns a jQuery set of ALL active result containers.
     * Covers: Gutenberg document panel, Gutenberg sidebar panel, Classic Editor fallback.
     */
    function getAllResultPanels() {
        var panels = $();
        var docPanel     = $('#picot_aio_optimizer-result-panel');
        var sidebarPanel = $('#picot_aio_optimizer-result-panel-sidebar');
        var classicPanel = $('#picot_aio_optimizer-classic-results');
        if (docPanel.length)     panels = panels.add(docPanel);
        if (sidebarPanel.length) panels = panels.add(sidebarPanel);
        if (classicPanel.length) panels = panels.add(classicPanel);
        return panels;
    }

    /**
     * Classic Editor meta box is rendered only when the block editor is off.
     * PHP passes is_block_editor; DOM check is a fallback.
     */
    function isClassicEditorMode() {
        if (typeof picot_aio_optimizer.is_post_editor !== 'undefined' && !picot_aio_optimizer.is_post_editor) {
            return false;
        }
        if (typeof picot_aio_optimizer.is_block_editor !== 'undefined') {
            return !picot_aio_optimizer.is_block_editor;
        }
        return $('#picot_aio_optimizer-classic-results').length > 0;
    }

    function hasGutenbergEditor() {
        return !isClassicEditorMode()
            && typeof wp !== 'undefined'
            && wp.data
            && typeof wp.data.select === 'function'
            && wp.data.select('core/editor');
    }

    function hasBlockEditorStore() {
        return hasGutenbergEditor()
            && typeof wp !== 'undefined'
            && wp.data
            && typeof wp.data.select === 'function'
            && wp.data.select('core/block-editor');
    }

    function setFeaturedImage(attachmentId) {
        if (!attachmentId) {
            return;
        }

        if (isClassicEditorMode()) {
            if (typeof wp !== 'undefined' && wp.media && wp.media.featuredImage) {
                wp.media.featuredImage.set(attachmentId);
            } else {
                $('#_thumbnail_id').val(attachmentId);
            }
            return;
        }

        if (hasGutenbergEditor()) {
            wp.data.dispatch('core/editor').editPost({ featured_media: attachmentId });
        }
    }

    function getEditorPostId() {
        var raw = 0;
        if (isClassicEditorMode()) {
            raw = $('#post_ID').val() || 0;
        } else if (hasGutenbergEditor()) {
            raw = wp.data.select('core/editor').getCurrentPostId() || ($('#post_ID').val() || 0);
        } else {
            raw = $('#post_ID').val() || 0;
        }
        return parseInt(raw, 10) || 0;
    }

    function requireEditorPostId() {
        var postId = getEditorPostId();
        if (!postId) {
            alert(picot_aio_optimizer.strings.post_id_required || 'Save or wait until the post draft is available, then try again.');
            return 0;
        }
        return postId;
    }

    function getEditorTitle() {
        if (isClassicEditorMode()) {
            return $('#title').val() || '';
        }
        if (hasGutenbergEditor()) {
            return wp.data.select('core/editor').getEditedPostAttribute('title') || '';
        }
        return $('#title').val() || '';
    }

    function getEditorContent() {
        if (isClassicEditorMode()) {
            return getClassicEditorContent();
        }

        if (hasGutenbergEditor()) {
            var postContent = wp.data.select('core/editor').getEditedPostContent();

            // DOM fallback if Gutenberg API returns empty (iframe editor / page builders)
            if (!postContent) {
                var editorCanvas = document.querySelector('iframe[name="editor-canvas"]');
                if (editorCanvas && editorCanvas.contentDocument) {
                    postContent = editorCanvas.contentDocument.body.innerText || '';
                } else {
                    var wrapper = document.querySelector('.editor-styles-wrapper') || document.querySelector('.block-editor-block-list__layout');
                    if (wrapper) {
                        postContent = wrapper.innerText || '';
                    }
                }
            }

            return postContent || '';
        }

        return getClassicEditorContent();
    }

    /**
     * REGISTER PLUGIN SIDEBAR (React/Gutenberg Native)
     * This avoids the PHP add_meta_box 500 error.
     */
    var domReady = (typeof wp !== 'undefined' && wp.domReady) ? wp.domReady : $;
    
    domReady(function() {
        if (isClassicEditorMode()) {
            return;
        }

        if (typeof wp !== 'undefined' && wp.plugins && (wp.editPost || wp.editor) && wp.element && wp.components) {
 //             console.log('Picot AIO Optimizer: Registering sidebar plugin...');
        
        var el = wp.element.createElement;
        var Fragment = wp.element.Fragment;
        var useState = wp.element.useState;
        
        // Use wp.editor (WP 6.6+), fallback to wp.editPost for legacy
        var editorNamespace = (typeof wp.editor !== 'undefined') ? wp.editor : wp.editPost;
        
        // Handle specific components that might have moved or exist in different namespaces
        var PluginSidebar                = editorNamespace.PluginSidebar || wp.editPost.PluginSidebar;
        var PluginSidebarMoreMenuItem    = editorNamespace.PluginSidebarMoreMenuItem || wp.editPost.PluginSidebarMoreMenuItem;
        var PluginDocumentSettingPanel   = editorNamespace.PluginDocumentSettingPanel || wp.editPost.PluginDocumentSettingPanel;
        
        var Button          = wp.components.Button;
        var PanelBody       = wp.components.PanelBody;
        var TextareaControl = wp.components.TextareaControl;

        /**
         * Shared content builder — used by both PluginDocumentSettingPanel and PluginSidebar.
         * resultPanelId differentiates the two result containers.
         */
        var buildPanelContent = function(rewriteInstructions, setRewriteInstructions, resultPanelId) {
            var nodes = [
                // Row 1: Analyze
                el('div', { style: { marginBottom: '15px' } },
                    el(Button, {
                        id: 'picot_aio_optimizer-analysis-btn',
                        isPrimary: true, isLarge: true,
                        style: { width: '100%', justifyContent: 'center' },
                        onClick: function() { analyzeContent(); }
                    }, picot_aio_optimizer.strings.analyze_btn || 'Gemini Analyze')
                ),
                // Row 2: Rewrite
                el('div', { style: { marginBottom: '15px', padding: '10px', background: '#f9f9f9', borderRadius: '4px', border: '1px solid #ddd' } },
                    el(TextareaControl, {
                        label: picot_aio_optimizer.strings.rewrite_instructions_label || 'Rewrite Instructions',
                        value: rewriteInstructions,
                        placeholder: picot_aio_optimizer.strings.rewrite_instructions_placeholder || 'e.g. Make it more professional...',
                        onChange: function(val) { setRewriteInstructions(val); }
                    }),
                    el(Button, {
                        id: 'picot_aio_optimizer-rewrite-btn',
                        isSecondary: true, isLarge: true,
                        style: { width: '100%', justifyContent: 'center' },
                        onClick: function() { triggerRewrite(rewriteInstructions); }
                    }, picot_aio_optimizer.strings.rewrite_btn || 'AI Rewrite')
                )
            ];

            if (isImageGenerationEnabled()) {
                nodes.push(
                    el('div', { style: { marginBottom: '15px' } },
                        el(Button, {
                            id: 'picot_aio_optimizer-discover-btn',
                            isSecondary: true, isLarge: true,
                            style: { width: '100%', justifyContent: 'center' },
                            onClick: function() { discoverImagePrompts(); }
                        }, picot_aio_optimizer.strings.discover_images_btn || 'Discover Image Placement Points')
                    )
                );
            }

            nodes.push(
                el('div', { id: resultPanelId, style: { marginTop: '10px' } }),
                el('div', { style: { marginTop: '20px', borderTop: '1px solid #ddd', paddingTop: '10px' } },
                    el(Button, {
                        isTertiary: true,
                        style: { width: '100%', justifyContent: 'center' },
                        onClick: function() { fetchPostHistory(); }
                    }, picot_aio_optimizer.strings.label_history || 'View History')
                )
            );

            return el.apply(null, [Fragment, {}].concat(nodes));
        };

        var PicotAioOptimizerSidebar = function() {
            // State for Document Setting Panel
            var docState = useState('');
            var docInstructions = docState[0];
            var setDocInstructions = docState[1];

            // State for Sidebar Panel (independent)
            var sideState = useState('');
            var sideInstructions = sideState[0];
            var setSideInstructions = sideState[1];

            return el(
                Fragment,
                {},
                // ── 1. Document Settings Panel (always visible in "Post" tab) ──
                el(
                    PluginDocumentSettingPanel,
                    {
                        name: 'picot-aio-doc-panel',
                        title: (picot_aio_optimizer.strings.plugin_title || 'Picot AIO Optimizer'),
                        icon: 'admin-site',
                        className: 'picot-aio-doc-panel'
                    },
                    buildPanelContent(docInstructions, setDocInstructions, 'picot_aio_optimizer-result-panel')
                ),
                // ── 2. More-menu item (globe icon) ──
                el(
                    PluginSidebarMoreMenuItem,
                    { target: 'picot-aio-optimizer-sidebar', icon: 'admin-site' },
                    (picot_aio_optimizer.strings.plugin_title || 'Picot AIO Optimizer')
                ),
                // ── 3. Plugin Sidebar (opened via globe icon) ──
                el(
                    PluginSidebar,
                    {
                        name: 'picot-aio-optimizer-sidebar',
                        title: (picot_aio_optimizer.strings.plugin_title || 'Picot AIO AI Content Optimizer'),
                        icon: 'admin-site'
                    },
                    el(
                        PanelBody,
                        { title: (picot_aio_optimizer.strings.controls_title || 'Optimizer Controls'), initialOpen: true },
                        buildPanelContent(sideInstructions, setSideInstructions, 'picot_aio_optimizer-result-panel-sidebar')
                    )
                )
            );
        };


        wp.plugins.registerPlugin('picot-aio-optimizer-sidebar-plugin', {
            render: PicotAioOptimizerSidebar
        });

        // Wait for the React sidebar panel to be rendered before initializing
        // Polls every 500ms, up to 10 times (5 seconds total)
        // NOTE: Must use var (function expression) inside an if-block in strict mode
        var waitForResultDiv = function(callback, maxRetries, interval) {
            maxRetries = maxRetries || 10;
            interval   = interval   || 500;
            var attempts = 0;

            var tryFind = function() {
                // Check all possible result containers (doc panel, sidebar panel, classic editor)
                var docPanel     = $('#picot_aio_optimizer-result-panel');
                var sidePanel    = $('#picot_aio_optimizer-result-panel-sidebar');
                var classicPanel = $('#picot_aio_optimizer-classic-results');

                if (docPanel.length > 0 || sidePanel.length > 0 || classicPanel.length > 0) {
                    callback();
                    return;
                }

                attempts++;
                if (attempts < maxRetries) {
                    setTimeout(tryFind, interval);
                } else {
                    debugLog('Result div not available after ' + maxRetries + ' retries — skipping auto-init', 'warn');
                }
            };

            setTimeout(tryFind, interval);
        };

        waitForResultDiv(function() {
            requestAnimationFrame(function() {
                $('#picot_aio_optimizer-result-panel').empty();
                $('#picot_aio_optimizer-classic-results').html('<p style="color:#666; font-style:italic;">' + (picot_aio_optimizer.strings.results_placeholder || 'Analysis results will appear here.') + '</p>');
                loadSavedImageSuggestions();
                fetchPostHistory(true);
            });
        });

        setTimeout(function() {
            setupPanelObserver();
        }, 1500);
        } // Close the if (wp.plugins...) block
    }); // Close the domReady callback

    // Global initialization for all admin pages (Settings and Post)
    $(function() {
        initSettingsPage();
    });

    // Setup observer to detect when panel content is cleared and re-render
    function setupPanelObserver() {
        // 二重起動を防ぐ（既存インターバルがあればそのまま使う）。
        if (window.PicotAioOptimizer && window.PicotAioOptimizer.panelObserverInterval) {
            return;
        }
        // Observe for panel being emptied by React re-renders
        var checkInterval = setInterval(function() {
            var panel = document.getElementById('picot_aio_optimizer-result-panel');
            if (panel && panel.innerHTML === '' && window.PicotAioOptimizer && window.PicotAioOptimizer.imageSuggestions && window.PicotAioOptimizer.imageSuggestions.length > 0) {
                // Re-render the suggestions
                var title = getEditorTitle();
                // Get the raw suggestions (without featured item which will be added by displayImageSuggestions)
                var rawSuggestions = window.PicotAioOptimizer.imageSuggestions.filter(function(item) {
                    return !item.isFeatured;
                });
                if (rawSuggestions.length > 0) {
                    displayImageSuggestions(
                        rawSuggestions,
                        title,
                        window.PicotAioOptimizer.lastFeaturedText || null,
                        window.PicotAioOptimizer.lastUpdated,
                        window.PicotAioOptimizer.lastFeaturedPrompt || null
                    );
                }
            }
        }, 500);
        
        // Store interval ID to allow cleanup if needed
        window.PicotAioOptimizer.panelObserverInterval = checkInterval;
    }

    // ==========================================
    // LOGIC FUNCTIONS (Adapted from existing)
    // ==========================================

    function triggerRewrite(instructions) {
        if (!confirm(picot_aio_optimizer.strings.confirm_rewrite)) return;
        rewrite_article(instructions);
    }

    function rewrite_article(instructions) {
 //         console.log('Picot AIO Optimizer: rewrite_article() called');
        var title = getEditorTitle();
        var content = getEditorContent();

        if (!content) {
 //             console.warn('Picot AIO Optimizer: No content found for rewrite');
            alert(picot_aio_optimizer.strings.no_content);
            return;
        }

        var postId = requireEditorPostId();
        if (!postId) {
            return;
        }

        var payload = {
            title: title || '',
            content: content || '',
            instructions: instructions || '',
            post_id: postId
        };

        var btn = $('#picot_aio_optimizer-rewrite-btn, #picot_aio_optimizer-classic-rewrite');
        var originalText = btn.length ? btn.first().text() : '';
        
        showOverlay(picot_aio_optimizer.strings.rewriting || 'Rewriting article...');
        btn.text(picot_aio_optimizer.strings.rewriting || 'Rewriting...').prop('disabled', true);

        $.ajax({
            url: picot_aio_optimizer.rest_url_rewrite,
            type: 'POST',
            contentType: 'application/json; charset=UTF-8', 
            beforeSend: function ( xhr ) {
                xhr.setRequestHeader( 'X-WP-Nonce', picot_aio_optimizer.rest_nonce );
            },
            data: JSON.stringify(payload),
            success: function(response) {
                var success = response.success || (response.data && response.data.title);
                var data = response.data || response;

                if (success) {
                    if (isClassicEditorMode()) {
                        if (data.title) {
                            $('#title').val(data.title);
                        }
                        setClassicEditorContent(data.content || '');
                    } else if (hasBlockEditorStore()) {
                        wp.data.dispatch('core/editor').editPost({ title: data.title });

                        if (wp.blocks && wp.blocks.rawHandler) {
                            var parsedBlocks = wp.blocks.rawHandler({ HTML: data.content });
                            if (parsedBlocks && parsedBlocks.length > 0) {
                                wp.data.dispatch('core/block-editor').resetBlocks(parsedBlocks);
                            } else {
                                wp.data.dispatch('core/editor').editPost({ content: data.content });
                            }
                        } else {
                            wp.data.dispatch('core/editor').editPost({ content: data.content });
                        }
                    } else {
                        if (data.title) {
                            $('#title').val(data.title);
                        }
                        setClassicEditorContent(data.content || '');
                    }

                    alert(picot_aio_optimizer.strings.success_rewrite);
                } else {
                    var errorMsg = picot_aio_optimizer.strings.error;
                    if (response.message) errorMsg += "\nServer Message: " + response.message;
                    alert(errorMsg);
                }
            },
            error: function(xhr) {
                // Robustness check: if status 200 but error triggered, it's likely "dirty" JSON (PHP notices at end)
                if (xhr.status === 200 && xhr.responseText) {
                    var responseText = xhr.responseText;
                    var jsonStart = responseText.indexOf('{');
                    var jsonEnd = responseText.lastIndexOf('}');
                    if (jsonStart !== -1 && jsonEnd !== -1 && jsonEnd > jsonStart) {
                        try {
                            var cleanJson = responseText.substring(jsonStart, jsonEnd + 1);
                            var response = JSON.parse(cleanJson);
                            // Call success manually with cleaned data
                            this.success(response);
                            return;
                        } catch(e) {
                            debugLog('Failed to recover from dirty JSON in Rewrite: ' + e.message, 'error');
                        }
                    }
                }
                alert(handleAjaxError(xhr, 'Rewrite'));
            },
            complete: function() {
                btn.text(originalText).prop('disabled', false);
                hideOverlay();
            }
        });
    }

    // NEW: Discover Image Prompt Opportunities
    function discoverImagePrompts() {
        if (!isImageGenerationEnabled()) {
            return;
        }
        var title = getEditorTitle();
        var content = getEditorContent();

        if (!content) {
 //             console.warn('Picot AIO Optimizer: No content found for image discovery');
            alert(picot_aio_optimizer.strings.no_content || 'No content found.');
            return;
        }

        var updatePanels = function(htmlStr) { getAllResultPanels().html(htmlStr); };

        var postId = requireEditorPostId();
        if (!postId) {
            return;
        }

        showOverlay(picot_aio_optimizer.strings.discovering || 'Discovering image opportunities...');
        updatePanels('<div class="picot_aio_optimizer-loading"><p>' + (picot_aio_optimizer.strings.processing || 'Processing...') + '</p></div>');

        var payload = {
            title: title || '',
            content: content || '',
            post_id: postId
        };

        $.ajax({
            url: picot_aio_optimizer.rest_url_suggest_images,
            type: 'POST',
            contentType: 'application/json; charset=UTF-8', 
            beforeSend: function ( xhr ) {
                xhr.setRequestHeader( 'X-WP-Nonce', picot_aio_optimizer.rest_nonce );
            },
            data: JSON.stringify(payload),
            success: function(response) {
                if (response.success && response.data) {
                    var suggestions = [];
                    var featuredText = '';
                    
                    // Handle new structure { featured_text: "...", suggestions: [...] }
                    if (response.data.suggestions) {
                        suggestions = response.data.suggestions;
                        featuredText = response.data.featured_text || '';
                    } else if (Array.isArray(response.data)) {
                        suggestions = response.data;
                    }

                    if (suggestions.length > 0) {
                        // Filter suggestions to avoid locations near existing images
                        var filteredSuggestions = filterSuggestionsNearImages(suggestions, true);
                        
                        if (filteredSuggestions.length > 0) {
                            // Save suggestions to post meta
                            saveImageSuggestions(filteredSuggestions, featuredText, response.data.featured_prompt);
                            
                            // Prepended list for UI (Featured + Suggestions)
                            var title = getEditorTitle();
                            // Pass featuredText as 3rd arg, updatedDate as 4th (undefined here), featuredPrompt as 5th
                            displayImageSuggestions(filteredSuggestions, title, featuredText, null, response.data.featured_prompt); 
    
                            whenClassicEditorReady(function() {
                                autoEmbedSuggestionsAtLocations(window.PicotAioOptimizer.imageSuggestions);
                            });
                        } else {
                            updatePanels('<div class="notice notice-warning"><p>' + (picot_aio_optimizer.strings.no_suggestions_near || 'No placement opportunities found') + '</p></div>');
                        }
                    } else {
                        updatePanels('<div class="notice notice-warning"><p>' + (picot_aio_optimizer.strings.no_suggestions || 'No suggestions found') + '</p></div>');
                    }
                } else {
                    updatePanels('<div class="notice notice-warning"><p>' + (picot_aio_optimizer.strings.no_suggestions || 'No suggestions found') + '</p></div>');
                }
            },
            error: function(xhr) {
                // Robustness check: if status 200 but error triggered, it's likely "dirty" JSON (PHP notices at end)
                if (xhr.status === 200 && xhr.responseText) {
                    var responseText = xhr.responseText;
                    var jsonStart = responseText.indexOf('{');
                    var jsonEnd = responseText.lastIndexOf('}');
                    if (jsonStart !== -1 && jsonEnd !== -1 && jsonEnd > jsonStart) {
                        try {
                            var cleanJson = responseText.substring(jsonStart, jsonEnd + 1);
                            var response = JSON.parse(cleanJson);
                            // Call success manually with cleaned data
                            this.success(response);
                            return;
                        } catch(e) {
                            debugLog('Failed to recover from dirty JSON in Discover: ' + e.message, 'error');
                        }
                    }
                }
                updatePanels('<div class="notice notice-error"><p>' + escapeHtml(handleAjaxError(xhr, 'Discover Images')) + '</p></div>');
            },
            complete: function() {
                hideOverlay();
            }
        });
    }

    /**
     * Filter suggestions to exclude locations near existing images and limit density
     */
    function filterSuggestionsNearImages(suggestions, includeFeatured) {
        try {
            if (isClassicEditorMode()) {
                return suggestions.filter(function(suggestion) {
                    return !suggestion.isFeatured;
                }).slice(0, 8);
            }

            if (!hasBlockEditorStore()) {
                return suggestions;
            }

            var blocks = wp.data.select('core/block-editor').getBlocks();
            var imageBlockIndices = [];
            
            blocks.forEach(function(block, index) {
                if (block.name === 'core/image' || block.name === 'core/gallery' || block.name === 'core/media-text') {
                    imageBlockIndices.push(index);
                }
            });

            var finalSuggestions = [];
            
            // Add a virtual featured image at the top if requested to ensure distance
            if (includeFeatured) {
                finalSuggestions.push({
                    isFeatured: true,
                    targetIdx: 0,
                    targetOffset: 0,
                    location: 'Featured Image'
                });
            }

            suggestions.forEach(function(suggestion) {
                if (suggestion.isFeatured) return;

                var matchInfo = findBlockIndexByText(blocks, suggestion.location || suggestion.description);
                if (!matchInfo) return;

                var targetIdx = matchInfo.index;
                var targetOffset = matchInfo.offset || 0;

                var isTooClose = false;
                
                // 1. Distance from EXISTING images (at least 2 blocks)
                for (var i = 0; i < imageBlockIndices.length; i++) {
                    if (Math.abs(imageBlockIndices[i] - targetIdx) <= 2) {
                        isTooClose = true; 
                        break;
                    }
                }
                
                // 2. Distance from OTHER new suggestions (including Featured Image at index 0)
                if (!isTooClose) {
                    for (var i = 0; i < finalSuggestions.length; i++) {
                        var other = finalSuggestions[i];
                        if (other.targetIdx === undefined) continue;
                        
                        if (other.targetIdx === targetIdx) {
                            if (Math.abs(other.targetOffset - targetOffset) < 400) { // Increased distance
                                isTooClose = true;
                                break;
                            }
                        } else if (Math.abs(other.targetIdx - targetIdx) <= 2) {
                            isTooClose = true;
                            break;
                        }
                    }
                }
                
                if (!isTooClose && finalSuggestions.length < 9) { // 1 featured + max 8 suggestions
                    suggestion.targetIdx = targetIdx;
                    suggestion.targetOffset = targetOffset;
                    finalSuggestions.push(suggestion);
                }
            });

            // Return only the suggestions (exclude the virtual featured item if added)
            return finalSuggestions.filter(function(s) { return !s.isFeatured; });
        } catch (e) {
            debugLog('Error filtering suggestions: ' + e.message, 'warn');
            return suggestions;
        }
    }

    /**
     * Show a full-screen loading overlay to prevent interaction
     */
    function showOverlay(message) {
 //         console.log('Picot AIO Optimizer: showOverlay() called with message: ' + message);
        // Remove existing overlay if any
        $('#picot_aio_optimizer-overlay').remove();
        
        var msg = message || (picot_aio_optimizer.strings.processing || 'Processing...');
        var overlayHtml = '<div id="picot_aio_optimizer-overlay">' +
                          '<div class="picot-spinner-container" style="position:relative; width:100px; height:100px; margin-bottom:30px;">' +
                          '<div class="picot-spinner-outer" style="position:absolute; top:0; left:0; width:100%; height:100%; border:4px solid transparent; border-top-color:#3b82f6; border-radius:50%; animation:picot-spin 1.5s linear infinite;"></div>' +
                          '<div class="picot-spinner-inner" style="position:absolute; top:15px; left:15px; width:70px; height:70px; border:4px solid transparent; border-top-color:#60a5fa; border-radius:50%; animation:picot-spin-reverse 1s linear infinite;"></div>' +
                          '<div class="picot-spinner-center" style="position:absolute; top:35px; left:35px; width:30px; height:30px; background:#3b82f6; border-radius:50%; box-shadow:0 0 20px #3b82f6; animation:picot-pulse 2s ease-in-out infinite;"></div>' +
                          '</div>' +
                          '<div style="font-size:24px; font-weight:600; letter-spacing:-0.025em; margin-bottom:10px; text-align:center;">' + escapeHtml(msg) + '</div>' +
                          '<div style="font-size:14px; color:rgba(255,255,255,0.6); font-weight:400;">' + escapeHtml(picot_aio_optimizer.strings.overlay_submessage || 'Please wait while AI processes your content...') + '</div>' +
                          '</div>';
        
        var $overlay = $(overlayHtml);
        $('body').append($overlay);
        
        // Force reflow and add active class
        $overlay[0].offsetHeight;
        $overlay.addClass('active');
        
        // Add a class to body to prevent scrolling
        $('body').css('overflow', 'hidden');
    }

    /**
     * Hide the loading overlay
     */
    function hideOverlay() {
        var $overlay = $('#picot_aio_optimizer-overlay');
        $overlay.css('opacity', '0');
        setTimeout(function() {
            $overlay.remove();
            $('body').css('overflow', '');
        }, 300);
    }

    // Save image suggestions for later use
    function saveImageSuggestions(suggestions, featuredText, featuredPrompt) {
        var postId = getEditorPostId();
        if (!postId) return;

        var payload = {
            suggestions: suggestions,
            featured_text: featuredText || '',
            featured_prompt: featuredPrompt || ''
        };

        $.ajax({
            url: picot_aio_optimizer.rest_url_save_suggestions,
            type: 'POST',
            beforeSend: function(xhr) {
                xhr.setRequestHeader('X-WP-Nonce', picot_aio_optimizer.rest_nonce);
            },
            data: {
                post_id: postId,
                suggestions: JSON.stringify(payload)
            }
        });
    }

    /**
     * Remove all existing suggestion markers from the editor content
     */
    function removeAllMarkers() {
        debugLog('Cleaning up existing markers...');
        
        try {
            if (isClassicEditorMode() && typeof tinymce !== 'undefined' && tinymce.activeEditor && !tinymce.activeEditor.isHidden()) {
                var classicContent = tinymce.activeEditor.getContent();
                if (classicContent.indexOf('picot_aio_optimizer-suggestion-marker') !== -1) {
                    var newClassicContent = classicContent.replace(/<div[^>]*class="picot_aio_optimizer-suggestion-marker"[^>]*><\/div>/g, '');
                    tinymce.activeEditor.setContent(newClassicContent);
                }
                return;
            }

            if (isClassicEditorMode()) {
                var textareaContent = getClassicEditorContent();
                if (textareaContent.indexOf('picot_aio_optimizer-suggestion-marker') !== -1) {
                    setClassicEditorContent(textareaContent.replace(/<div[^>]*class="picot_aio_optimizer-suggestion-marker"[^>]*><\/div>/g, ''));
                }
                return;
            }

            if (hasBlockEditorStore()) {
                var editor = wp.data.dispatch('core/block-editor');
                var select = wp.data.select('core/block-editor');
                var blocks = select.getBlocks();

                blocks.forEach(function(block) {
                    if (block.name === 'core/freeform') {
                        var html = block.attributes.content || '';
                        if (html.indexOf('picot_aio_optimizer-suggestion-marker') !== -1) {
                            var newHtml = html.replace(/<div[^>]*class="picot_aio_optimizer-suggestion-marker"[^>]*><\/div>/g, '');
                            if (newHtml !== html) {
                                editor.updateBlockAttributes(block.clientId, { content: newHtml });
                            }
                        }
                    } else if (block.name === 'core/html') {
                        var markerHtml = block.attributes.content || '';
                        if (markerHtml.indexOf('picot_aio_optimizer-suggestion-marker') !== -1) {
                            editor.removeBlock(block.clientId);
                        }
                    }
                });
            }
        } catch (e) {
            debugLog('Failed to clean markers: ' + e.message, 'warn');
        }
    }

    /**
     * Remove the Featured Image Prompt box generated during rewrite
     */
    function removeFeaturedImagePrompt() {
        debugLog('Cleaning up Featured Image Prompt...');
        try {
            if (isClassicEditorMode() && typeof tinymce !== 'undefined' && tinymce.activeEditor && !tinymce.activeEditor.isHidden()) {
                var classicPromptContent = tinymce.activeEditor.getContent();
                if (classicPromptContent.indexOf('background:#e6eeff') !== -1 && classicPromptContent.indexOf('border:2px solid #4d4dff') !== -1) {
                    var cleanedClassicContent = classicPromptContent.replace(/<div[^>]*style="[^"]*background:#e6eeff[^>]*>[\s\S]*?<\/div>/i, '');
                    tinymce.activeEditor.setContent(cleanedClassicContent);
                }
                return;
            }

            if (isClassicEditorMode()) {
                var classicTextareaContent = getClassicEditorContent();
                if (classicTextareaContent.indexOf('background:#e6eeff') !== -1 && classicTextareaContent.indexOf('border:2px solid #4d4dff') !== -1) {
                    setClassicEditorContent(classicTextareaContent.replace(/<div[^>]*style="[^"]*background:#e6eeff[^>]*>[\s\S]*?<\/div>/i, ''));
                }
                return;
            }

            if (hasBlockEditorStore()) {
                var editor = wp.data.dispatch('core/block-editor');
                var select = wp.data.select('core/block-editor');
                var blocks = select.getBlocks();
                
                blocks.forEach(function(block) {
                    if (block.name === 'core/html' || block.name === 'core/freeform') {
                        var html = block.attributes.content || '';
                        if (html.indexOf('background:#e6eeff') !== -1 && html.indexOf('border:2px solid #4d4dff') !== -1) {
                            if (block.name === 'core/html') {
                                editor.removeBlock(block.clientId);
                            } else {
                                var newHtml = html.replace(/<div[^>]*style="[^"]*background:#e6eeff[^>]*>[\s\S]*?<\/div>/i, '');
                                editor.updateBlockAttributes(block.clientId, { content: newHtml });
                            }
                        }
                    }
                });
            }
        } catch (e) {
            debugLog('Failed to clean Featured Image Prompt: ' + e.message, 'warn');
        }
    }

    // Automatically embed suggestions as individual hidden markers at specific locations
    function autoEmbedSuggestionsAtLocations(suggestions) {
        if (!suggestions || suggestions.length === 0) return;

        // Clean up first!
        removeAllMarkers();
        
        try {
            var createBlock = (typeof wp !== 'undefined' && wp.blocks) ? wp.blocks.createBlock : null;
            var editor = hasBlockEditorStore() ? wp.data.dispatch('core/block-editor') : null;
            var select = hasBlockEditorStore() ? wp.data.select('core/block-editor') : null;
            var embeddedCount = 0;

            // 1. Classic Editor Case
            if (isClassicEditorMode()) {
                var classicEmbedContent = getClassicEditorContent();
                suggestions.forEach(function(suggestion, index) {
                    if (suggestion.isFeatured) return;
                    var markerHtml = '<div class="picot_aio_optimizer-suggestion-marker" data-index="' + index + '" style="display:none;"><!-- PICOT_AIO_OPTIMIZER_MARKER:' + index + ' --></div>';
                    classicEmbedContent = insertMarkerIntoHtmlString(classicEmbedContent, suggestion.location || suggestion.description, markerHtml);
                    if (window.PicotAioOptimizer.imageSuggestions[index]) {
                        window.PicotAioOptimizer.imageSuggestions[index].hasPlaceholder = true;
                    }
                    embeddedCount++;
                });
                setClassicEditorContent(classicEmbedContent);
            }
            // 2. Gutenberg Case
            else if (editor && select && createBlock) {
                var blocks = select.getBlocks();
                // Remove any existing markers first
                var existingMarkers = blocks.filter(function(b) {
                    return b.name === 'core/html' && b.attributes.content && b.attributes.content.indexOf('picot_aio_optimizer-suggestion-marker') !== -1;
                });
                if (existingMarkers.length > 0) {
                    editor.removeBlocks(existingMarkers.map(function(b) { return b.clientId; }));
                }

                suggestions.forEach(function(suggestion, index) {
                    if (suggestion.isFeatured) return;

                    var matchInfo = findBlockIndexByText(blocks, suggestion.location || suggestion.description);
                    if (matchInfo) {
                        var markerHtml = '<div class="picot_aio_optimizer-suggestion-marker" data-index="' + index + '" style="display:none; visibility:hidden; height:0; overflow:hidden;"><!-- PICOT_AIO_OPTIMIZER_MARKER:' + index + ' --></div>';
                        var targetBlock = select.getBlock(matchInfo.clientId);

                        if (targetBlock && targetBlock.name === 'core/freeform') {
                            // CLASSIC BLOCK: Inject marker into HTML
                            debugLog('Injecting marker ' + index + ' into Classic Block HTML');
                            var oldHtml = targetBlock.attributes.content || '';
                            var newHtml = insertMarkerIntoHtmlString(oldHtml, suggestion.location || suggestion.description, markerHtml);
                            editor.updateBlockAttributes(matchInfo.clientId, { content: newHtml });
                        } else {
                            // NORMAL BLOCK: Insert after block
                            var markerBlock = createBlock('core/html', { content: markerHtml });
                            var currentIndex = select.getBlockIndex(matchInfo.clientId, matchInfo.rootClientId);
                            editor.insertBlock(markerBlock, currentIndex + 1, matchInfo.rootClientId, false);
                        }
                        
                        if (window.PicotAioOptimizer.imageSuggestions[index]) {
                            window.PicotAioOptimizer.imageSuggestions[index].hasPlaceholder = true;
                        }
                        embeddedCount++;
                    }
                });
            }

            debugLog('Auto-embedded ' + embeddedCount + ' markers');
        } catch (e) {
            debugLog('Failed to auto-embed suggestions: ' + e.message, 'error');
        }
    }

    // Helper to inject a marker string into a raw HTML string
    function insertMarkerIntoHtmlString(html, targetText, markerHtml) {
        var cleanTarget = stripHtml(targetText).trim();
        if (cleanTarget.length < 3) return html + markerHtml;

        var pos = html.indexOf(cleanTarget);
        if (pos === -1) {
            pos = html.indexOf(cleanTarget.substring(0, 10));
        }

        if (pos !== -1) {
            var endTag = html.indexOf('</p>', pos);
            if (endTag === -1) endTag = html.indexOf('</div>', pos);
            if (endTag === -1) endTag = html.indexOf('\n', pos);

            if (endTag !== -1) {
                var offset = (html.substr(endTag, 4) === '</p>') ? 4 : 0;
                if (offset === 0 && html.substr(endTag, 6) === '</div>') offset = 6;
                var splitAt = endTag + offset;
                return html.substring(0, splitAt) + markerHtml + html.substring(splitAt);
            }
        }
        return html + markerHtml;
    }

    // Utility to strip HTML tags from a string
    function stripHtml(html) {
        if (!html) return '';
        // innerHTML はパース時に onerror 等を発火させ得るため使わない。
        // DOMParser で解析し、テキストのみ取り出す（スクリプトは実行されない）。
        try {
            var doc = new DOMParser().parseFromString(String(html), 'text/html');
            return (doc.body && (doc.body.textContent || '')) || '';
        } catch (e) {
            return String(html).replace(/<[^>]*>/g, '');
        }
    }

    // Helper to find block index by matching text content (robust smart matching)
    function findBlockIndexByText(blocks, searchText, parentIndex, parentClientId) {
        if (!searchText || !blocks || blocks.length === 0) return null;
        parentIndex = (parentIndex === undefined) ? -1 : parentIndex;
        
        debugLog('--- Starting Block Search ---');
        debugLog('Search Text: ' + searchText);

        var coreTarget = '';
        var quoteMatch = searchText.match(/[「"'](.+?)[」"']/);
        if (quoteMatch && quoteMatch[1]) {
            coreTarget = quoteMatch[1];
        } else {
            coreTarget = searchText.replace(/(の(段落|セクション|リスト|テーブル|最後|直前|後|部分|メッセージ|テキスト|見出し)).*$/, '');
        }

        var cleanTarget = stripHtml(coreTarget).toLowerCase().replace(/[.,!?;:()\[\]「」""'' \n\t　、。！？」]/g, '').trim();
        debugLog('Clean Target: ' + cleanTarget);
        
        if (cleanTarget.length < 2) return null;

        var snippets = [
            cleanTarget,
            cleanTarget.substring(0, 8),
            cleanTarget.length > 8 ? cleanTarget.substring(cleanTarget.length - 8) : cleanTarget
        ];

        for (var i = 0; i < blocks.length; i++) {
            var block = blocks[i];
            var contentParts = [];
            if (block.attributes) {
                var a = block.attributes;
                if (a.content) contentParts.push(a.content);
                if (a.values) contentParts.push(Array.isArray(a.values) ? a.values.join('') : a.values);
                if (a.value) contentParts.push(a.value);
                if (a.text) contentParts.push(a.text);
            }
            
            var rawContent = contentParts.join(' ');
            var cleanContent = stripHtml(rawContent).toLowerCase().replace(/[.,!?;:()\[\]「」""'' \n\t　、。！？」]/g, '').trim();
            
            if (cleanContent.length > 0) {
                debugLog('Checking Block ' + i + ' (' + block.name + '): ' + cleanContent.substring(0, 30) + '...');
            }

            var isMatch = false;
            var matchOffset = 0;
            for (var s = 0; s < snippets.length; s++) {
                if (snippets[s].length >= 3) {
                    var pos = cleanContent.indexOf(snippets[s]);
                    if (pos !== -1) {
                        isMatch = true;
                        matchOffset = pos;
                        debugLog('>>> MATCH FOUND! Snippet: ' + snippets[s]);
                        break;
                    }
                    if (snippets[s].indexOf(cleanContent) !== -1) {
                        isMatch = true;
                        matchOffset = 0;
                        break;
                    }
                }
            }

            if (isMatch) {
                var rootClientId = null;
                if (hasBlockEditorStore()) {
                    rootClientId = parentClientId || wp.data.select('core/block-editor').getBlockRootClientId(block.clientId);
                }
                return {
                    clientId: block.clientId,
                    index: parentIndex >= 0 ? parentIndex : i,
                    rootClientId: rootClientId,
                    offset: matchOffset
                };
            }

            if (block.innerBlocks && block.innerBlocks.length > 0) {
                var found = findBlockIndexByText(block.innerBlocks, searchText, i, block.clientId);
                if (found) return found;
            }
        }
        
        debugLog('--- Search Finished: No Match ---');
        return null;
    }

    // Load saved image suggestions on post load
    function loadSavedImageSuggestions() {
        if (!isImageGenerationEnabled()) {
            return;
        }
        var postId = $('#post_ID').val();
        if (!postId) return;

        var separator = picot_aio_optimizer.rest_url_load_suggestions.indexOf('?') !== -1 ? '&' : '?';
        $.ajax({
            url: picot_aio_optimizer.rest_url_load_suggestions + separator + 'post_id=' + postId,
            type: 'GET',
            beforeSend: function(xhr) {
                xhr.setRequestHeader('X-WP-Nonce', picot_aio_optimizer.rest_nonce);
            },
            success: function(response) {
                if (response.success && response.data) {
                    var data = response.data;
                    
                    // Handle if data is a string (JSON encoded)
                    if (typeof data === 'string' && data.length > 0) {
                        try {
                            data = JSON.parse(data);
                        } catch (e) {
                            debugLog('Failed to parse saved suggestions: ' + e.message, 'warn');
                            return;
                        }
                    }
                    
                    if (data && data.suggestions) {
                        var title = getEditorTitle();
                        displayImageSuggestions(data.suggestions, title, data.featured_text, response.updated, data.featured_prompt);
                        whenClassicEditorReady(function() {
                            autoEmbedSuggestionsAtLocations(window.PicotAioOptimizer.imageSuggestions);
                        });
                    } else if (Array.isArray(data) && data.length > 0) {
                        // Compatibility for old format
                        var title = getEditorTitle();
                        displayImageSuggestions(data, title, null, response.updated);
                    }
                }
            }
        });
    }

    function displayImageSuggestions(suggestions, postTitle, featuredText, updatedDate, featuredPrompt) {
        if (!isImageGenerationEnabled()) {
            return;
        }
        var updatePanels = function(htmlStr) { getAllResultPanels().html(htmlStr); };
        
        // Always add Featured Image as first item
        var featuredPromptText = featuredText ? featuredText : (postTitle || 'Blog Post');
        
        var finalFeaturedPrompt = featuredPrompt ? featuredPrompt : 
                                  'A professional blog featured image (thumbnail). STYLE: Modern Typography Design, Poster Art. ACTION: Render the text "' + featuredPromptText + '" in large, clear, bold characters that EXACTLY MATCH THE LANGUAGE of the article title. Use the same script and language as the title text - do not translate or convert. BACKGROUND: Clean, minimal, high contrast. No garbage characters.';

        var featuredItem = {
            location: picot_aio_optimizer.strings.featured_image || 'Featured Image',
            prompt: finalFeaturedPrompt,
            description: picot_aio_optimizer.strings.featured_image_prompt || 'Generate featured image',
            featured_text: featuredText || '',
            isFeatured: true
        };

        var allSuggestions = [featuredItem].concat(suggestions);
        window.PicotAioOptimizer.imageSuggestions = allSuggestions;
        window.PicotAioOptimizer.lastUpdated = updatedDate || null;
        window.PicotAioOptimizer.lastFeaturedText = featuredText || '';
        window.PicotAioOptimizer.lastFeaturedPrompt = featuredPrompt || finalFeaturedPrompt;

        var html = '<div style="font-size:13px; color:#1d2327;">';
        html += '<h3 style="margin-top:0; margin-bottom:15px; padding-bottom:8px; border-bottom:1px solid #ccd0d4; font-size:14px;">🖼️ ' + (picot_aio_optimizer.strings.image_opportunities || picot_aio_optimizer.strings.discover_images_btn || 'Image Opportunities') + '</h3>';
        
        // Show saved date if available
        if (updatedDate) {
            html += '<p style="font-size:11px; color:#646970; margin-top:-5px; margin-bottom:15px;">' + (picot_aio_optimizer.strings.save_date || 'Saved: ') + escapeHtml(updatedDate) + '</p>';
        }

        // 画像生成が無効なら生成ボタンは出さず、プロンプトの提示のみに留める。
        var imageGenEnabled = isImageGenerationEnabled();

        allSuggestions.forEach(function(item, index) {
            var borderColor = item.isFeatured ? '#f56e28' : '#2271b1';
            html += '<div style="margin-bottom:20px; padding-left:10px; border-left:3px solid ' + borderColor + ';">';
            html += '<strong style="display:block; margin-bottom:5px; font-size:13px; color:' + borderColor + ';">' + (item.isFeatured ? '⭐ ' : '📍 ') + escapeHtml(item.location || item.description) + '</strong>';
            html += '<div style="line-height:1.5; margin-bottom:8px;">' + escapeHtml(item.description || '') + '</div>';

            if (imageGenEnabled) {
                html += '<div style="display:flex; gap:8px; flex-wrap:wrap;">';
                // Single combined button: Generate and Place
                html += '<button type="button" class="button button-primary picot_aio_optimizer-gen-single-btn" data-index="' + index + '">' + (picot_aio_optimizer.strings.gen_and_place || 'Generate and Place') + '</button>';
                html += '</div>';
            }

            html += '</div>';
        });

        if (imageGenEnabled) {
            html += '<div style="margin-top:20px; padding-top:15px; border-top:1px solid #ccd0d4;">';
            html += '<button type="button" class="button button-primary" id="picot_aio_optimizer-generate-all" style="width:100%;">' + (picot_aio_optimizer.strings.gen_all || 'Batch Generate and Place') + '</button>';
            html += '</div>';
        }

        html += '</div>';
        updatePanels(html);

        // Bind event handlers
        $(document).off('click', '.picot_aio_optimizer-gen-single-btn').on('click', '.picot_aio_optimizer-gen-single-btn', function() {
            var idx = $(this).data('index');
            generateSingleImage(idx, $(this));
        });

        // Generate all images button handler
        $(document).off('click', '#picot_aio_optimizer-generate-all').on('click', '#picot_aio_optimizer-generate-all', function() {
            generateAllImages();
        });
    }

    // Generate a single image from suggestion
    function generateSingleImage(index, $btn) {
        var suggestion = window.PicotAioOptimizer.imageSuggestions[index];
        if (!suggestion) return;

        var postId = requireEditorPostId();
        if (!postId) {
            return;
        }

        var originalText = $btn.text();
        showOverlay(picot_aio_optimizer.strings.generating_image || picot_aio_optimizer.strings.generating || 'Generating image...');
        $btn.text(picot_aio_optimizer.strings.generating || 'Generating...').prop('disabled', true);

        var fullPrompt = suggestion.prompt;
        if (picot_aio_optimizer.image_style_desc) {
            fullPrompt += ". Style: " + picot_aio_optimizer.image_style_desc;
        }

        $.ajax({
            url: picot_aio_optimizer.rest_url_generate_image,
            type: 'POST',
            beforeSend: function(xhr) {
                xhr.setRequestHeader('X-WP-Nonce', picot_aio_optimizer.rest_nonce);
            },
            data: {
                prompt: fullPrompt,
                post_id: postId
            },
            success: function(response) {
                if (response.success && response.data) {
                    var imageUrl = response.data.url;
                    var attachmentId = response.data.attachment_id || response.data.id;

                    var placed = false;

                    if (suggestion.isFeatured && attachmentId) {
                        setFeaturedImage(attachmentId);
                        placed = insertImageBlockAtCursor(attachmentId, imageUrl, suggestion.description || '', 'START_OF_POST');
                        if (placed) {
                            removeFeaturedImagePrompt();
                        }
                    } else {
                        var replaced = replacePlaceholderWithImage(index, attachmentId, imageUrl, suggestion.description || '');
                        if (replaced) {
                            placed = true;
                        } else {
                            var targetText = suggestion.location || suggestion.description || '';
                            placed = insertImageBlockAtCursor(attachmentId, imageUrl, suggestion.description || '', targetText);
                        }
                    }

                    if (!placed) {
                        alert(picot_aio_optimizer.strings.insert_failed || 'Failed to insert image.');
                        return;
                    }

                    window.PicotAioOptimizer.imageSuggestions.splice(index, 1);
                    
                    var cleanSuggestions = window.PicotAioOptimizer.imageSuggestions.filter(function(s) { return !s.isFeatured; });
                    var feat = window.PicotAioOptimizer.imageSuggestions.find(function(s) { return s.isFeatured; });
                    saveImageSuggestions(cleanSuggestions, feat ? feat.featured_text : null, feat ? feat.prompt : null);
                    
                    var title = getEditorTitle();
                    displayImageSuggestions(cleanSuggestions, title, feat ? feat.featured_text : null, null, feat ? feat.prompt : null);
                } else {
                    alert((picot_aio_optimizer.strings.generation_failed || 'Generation failed') + ': ' + (response.message || picot_aio_optimizer.strings.unknown_error || 'Unknown error'));
                }
            },
            error: function(xhr) {
                var errorMsg = picot_aio_optimizer.strings.generation_failed || 'Generation failed';
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    errorMsg = xhr.responseJSON.message;
                }
                alert(errorMsg);
            },
            complete: function() {
                $btn.text(originalText).prop('disabled', false);
                hideOverlay();
            }
        });
    }

    // Insert image block or HTML at specific location
    function insertImageBlockAtCursor(attachmentId, imageUrl, altText, targetText) {
        var imgHtml = '\n<figure class="wp-block-image size-large"><img src="' + escapeHtml(imageUrl) + '" alt="' + escapeHtml(altText || '') + '" class="wp-image-' + encodeURIComponent(attachmentId) + '"/></figure>\n';
        
        try {
            if (isClassicEditorMode()) {
                var classicContent = getClassicEditorContent();
                var updatedClassicContent = '';
                if (targetText === 'START_OF_POST') {
                    var trimmedClassic = classicContent.trim();
                    if (trimmedClassic.indexOf('<img') === 0 || trimmedClassic.indexOf('<figure') === 0 || trimmedClassic.indexOf('<div class="wp-block-image') === 0) {
                        return false;
                    }
                    updatedClassicContent = imgHtml + classicContent;
                } else {
                    updatedClassicContent = insertImageIntoHtmlString(classicContent, targetText, imageUrl, altText, attachmentId);
                }
                setClassicEditorContent(updatedClassicContent);
                return true;
            }

            if (hasBlockEditorStore()) {
                var editor = wp.data.dispatch('core/block-editor');
                var select = wp.data.select('core/block-editor');
                var blocks = select.getBlocks();
                
                if (targetText === 'START_OF_POST') {
                    debugLog('Checking for existing image at start of post...');
                    if (blocks.length > 0 && (blocks[0].name === 'core/image' || blocks[0].name === 'core/cover')) {
                        return false;
                    }
                    
                    debugLog('Inserting image at the start of post.');
                    var imageBlock = wp.blocks.createBlock('core/image', {
                        id: attachmentId,
                        url: imageUrl,
                        alt: altText || ''
                    });
                    editor.insertBlock(imageBlock, 0, undefined, false);
                    return true;
                }

                debugLog('Gutenberg detected. Searching for: ' + targetText);
                var matchInfo = findBlockIndexByText(blocks, targetText);
                
                if (matchInfo) {
                    var targetBlock = select.getBlock(matchInfo.clientId);
                    
                    if (targetBlock && targetBlock.name === 'core/freeform') {
                        debugLog('Target is a Classic Block. Injecting into HTML content.');
                        var oldHtml = targetBlock.attributes.content || '';
                        var newHtml = insertImageIntoHtmlString(oldHtml, targetText, imageUrl, altText, attachmentId);
                        editor.updateBlockAttributes(matchInfo.clientId, { content: newHtml });
                        return true;
                    }

                    var currentIndex = select.getBlockIndex(matchInfo.clientId, matchInfo.rootClientId);
                    var imageBlock = wp.blocks.createBlock('core/image', {
                        id: attachmentId,
                        url: imageUrl,
                        alt: altText || ''
                    });
                    editor.insertBlock(imageBlock, currentIndex + 1, matchInfo.rootClientId, false);
                    return true;
                }
            }

            if (window.send_to_editor) {
                window.send_to_editor(imgHtml);
                return true;
            }

            return false;

        } catch (e) {
            debugLog('Insertion failed: ' + e.message, 'error');
            return false;
        }
    }

    // Helper to inject image HTML into a raw HTML string based on text matching
    function insertImageIntoHtmlString(html, targetText, imageUrl, altText, attachmentId) {
        if (!targetText) return html + '\n<img src="' + escapeHtml(imageUrl) + '" alt="' + escapeHtml(altText || '') + '" class="aligncenter" />\n';

        var cleanTarget = stripHtml(targetText).trim();
        if (cleanTarget.length < 3) return html + '\n<img src="' + escapeHtml(imageUrl) + '" alt="' + escapeHtml(altText || '') + '" class="aligncenter" />\n';

        var imgHtml = '\n<figure class="wp-block-image aligncenter size-large"><img src="' + escapeHtml(imageUrl) + '" alt="' + escapeHtml(altText || '') + '" class="wp-image-' + encodeURIComponent(attachmentId) + '"/></figure>\n';

        // Try to find target text position
        var pos = html.indexOf(cleanTarget);
        if (pos === -1) {
            // Try very fuzzy (first 10 chars)
            pos = html.indexOf(cleanTarget.substring(0, 10));
        }

        if (pos !== -1) {
            // Find end of paragraph or current tag
            var endTag = html.indexOf('</p>', pos);
            if (endTag === -1) endTag = html.indexOf('</div>', pos);
            if (endTag === -1) endTag = html.indexOf('\n', pos);

            if (endTag !== -1) {
                var offset = (html.substr(endTag, 4) === '</p>') ? 4 : 0;
                if (offset === 0 && html.substr(endTag, 6) === '</div>') offset = 6;
                
                var splitAt = endTag + offset;
                return html.substring(0, splitAt) + imgHtml + html.substring(splitAt);
            }
        }

        return html + imgHtml; // Append if not found
    }

    // Find and replace a hidden marker with an image
    function replacePlaceholderWithImage(index, attachmentId, imageUrl, altText) {
        try {
            if (isClassicEditorMode()) {
                var classicContent = getClassicEditorContent();
                var markerPattern = new RegExp('<div[^>]*class="picot_aio_optimizer-suggestion-marker"[^>]*data-index=["\']' + index + '["\'][^>]*>[\\s\\S]*?</div>', 'i');
                var imageFigure = '<figure class="wp-block-image size-large"><img src="' + escapeHtml(imageUrl) + '" alt="' + escapeHtml(altText || '') + '" class="wp-image-' + encodeURIComponent(attachmentId) + '"/></figure>';

                if (markerPattern.test(classicContent)) {
                    setClassicEditorContent(classicContent.replace(markerPattern, imageFigure));
                    return true;
                }
            }

            if (!hasBlockEditorStore()) {
                return false;
            }

            var blocks = wp.data.select('core/block-editor').getBlocks();
            var regexMarker = new RegExp('<!--\\s*PICOT_AIO_OPTIMIZER_MARKER:' + index + '\\s*-->', 'i');
            var regexDataIndex = new RegExp('data-index=["\']' + index + '["\']', 'i');
            
            return findAndReplaceBlockRecursively(blocks, regexMarker, regexDataIndex, attachmentId, imageUrl, altText);
        } catch (e) {
            debugLog('Failed to replace marker: ' + e.message, 'error');
            return false;
        }
    }

    // Recursive helper to find and replace the marker block
    function findAndReplaceBlockRecursively(blocks, regexMarker, regexDataIndex, attachmentId, imageUrl, altText) {
        for (var i = 0; i < blocks.length; i++) {
            var block = blocks[i];
            
            if (block.name === 'core/html' && block.attributes && block.attributes.content) {
                var content = block.attributes.content;
                if (regexMarker.test(content) || regexDataIndex.test(content)) {
                    var createBlock = wp.blocks.createBlock;
                    var replaceBlock = wp.data.dispatch('core/block-editor').replaceBlock;
                    
                    var imageBlock = createBlock('core/image', {
                        id: attachmentId,
                        url: imageUrl,
                        alt: altText || '',
                        caption: ''
                    });
                    
                    // replaceBlock auto-selects the new block; use remove+insert with updateSelection=false instead
                    var blockIndex = wp.data.select('core/block-editor').getBlockIndex(block.clientId);
                    var rootClientId = wp.data.select('core/block-editor').getBlockRootClientId(block.clientId);
                    wp.data.dispatch('core/block-editor').removeBlock(block.clientId, false);
                    wp.data.dispatch('core/block-editor').insertBlock(imageBlock, blockIndex, rootClientId, false);
                    return true;
                }
            }

            if (block.innerBlocks && block.innerBlocks.length > 0) {
                if (findAndReplaceBlockRecursively(block.innerBlocks, regexMarker, regexDataIndex, attachmentId, imageUrl, altText)) {
                    return true;
                }
            }
        }
        return false;
    }

    // Sequential generation for all images
    function generateAllImages() {
        var postId = requireEditorPostId();
        if (!postId) {
            return;
        }

        var suggestions = window.PicotAioOptimizer.imageSuggestions || [];
        var indicesToProcess = [];

        suggestions.forEach(function(suggestion, index) {
            indicesToProcess.push(index);
        });

        if (indicesToProcess.length === 0) {
            alert(picot_aio_optimizer.strings.no_suggestions || 'No suggestions found.');
            return;
        }

        var totalCount = indicesToProcess.length;
        var currentIndex = 0;
        var successCount = 0;
        var completedIndices = [];
        var $btn = $('#picot_aio_optimizer-generate-all');
        var originalBtnText = $btn.text();
        
        showOverlay((picot_aio_optimizer.strings.batch_progress || 'Generating... ') + '(0/' + totalCount + ')');
        $btn.text((picot_aio_optimizer.strings.batch_progress || 'Generating... ') + '(0/' + totalCount + ')').prop('disabled', true);

        function processNext() {
            var idx = indicesToProcess[currentIndex];
            var suggestion = suggestions[idx];
            var progressText = (picot_aio_optimizer.strings.batch_progress || 'Generating... ') + '(' + (currentIndex + 1) + '/' + totalCount + ')';
            $btn.text(progressText);
            showOverlay(progressText); // Update overlay message

            var fullPrompt = suggestion.prompt;
            if (picot_aio_optimizer.image_style_desc) {
                fullPrompt += ". Style: " + picot_aio_optimizer.image_style_desc;
            }

            $.ajax({
                url: picot_aio_optimizer.rest_url_generate_image,
                type: 'POST',
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', picot_aio_optimizer.rest_nonce);
                },
                data: { prompt: fullPrompt, post_id: postId },
                success: function(response) {
                    if (response.success && response.data) {
                        var attachmentId = response.data.attachment_id || response.data.id;
                        var imageUrl = response.data.url;
                        var placed = false;
                        
                        if (suggestion.isFeatured && attachmentId) {
                            setFeaturedImage(attachmentId);
                            placed = insertImageBlockAtCursor(attachmentId, imageUrl, suggestion.description || '', 'START_OF_POST');
                            if (placed) {
                                removeFeaturedImagePrompt();
                            }
                        } else {
                            var replaced = replacePlaceholderWithImage(idx, attachmentId, imageUrl, suggestion.description);
                            if (replaced) {
                                placed = true;
                            } else {
                                var targetText = suggestion.location || suggestion.description || '';
                                placed = insertImageBlockAtCursor(attachmentId, imageUrl, suggestion.description || '', targetText);
                            }
                        }

                        if (placed) {
                            successCount++;
                            completedIndices.push(idx);
                        }
                    }
                },
                complete: function() {
                    currentIndex++;
                    if (currentIndex >= indicesToProcess.length) {
                        $btn.text(originalBtnText).prop('disabled', false);
                        hideOverlay();

                        if (successCount === totalCount) {
                            clearImageSuggestions();
                        } else if (successCount > 0) {
                            completedIndices.sort(function(a, b) { return b - a; }).forEach(function(removeIdx) {
                                window.PicotAioOptimizer.imageSuggestions.splice(removeIdx, 1);
                            });

                            var title = getEditorTitle();
                            var feat = window.PicotAioOptimizer.imageSuggestions.find(function(s) { return s.isFeatured; });
                            var cleanSuggestions = window.PicotAioOptimizer.imageSuggestions.filter(function(s) { return !s.isFeatured; });
                            saveImageSuggestions(cleanSuggestions, feat ? feat.featured_text : null, feat ? feat.prompt : null);
                            displayImageSuggestions(cleanSuggestions, title, feat ? feat.featured_text : null, window.PicotAioOptimizer.lastUpdated, feat ? feat.prompt : null);
                            alert((picot_aio_optimizer.strings.batch_progress || 'Generating... ') + successCount + '/' + totalCount + ' ' + (picot_aio_optimizer.strings.batch_done || 'Done!'));
                        } else {
                            alert(picot_aio_optimizer.strings.error || 'Error occurred.');
                        }
                    } else {
                        setTimeout(processNext, 500);
                    }
                }
            });
        }
        processNext();
    }

    // Clear saved suggestions from DB and UI
    function clearImageSuggestions() {
        var postId = $('#post_ID').val();
        if (!postId) return;

        $.ajax({
            url: picot_aio_optimizer.rest_url_save_suggestions,
            type: 'POST',
            beforeSend: function(xhr) {
                xhr.setRequestHeader('X-WP-Nonce', picot_aio_optimizer.rest_nonce);
            },
            data: {
                post_id: postId,
                suggestions: '' // Empty string clears the meta
            },
            success: function() {
                window.PicotAioOptimizer.imageSuggestions = [];
                var html = '<div class="notice notice-success"><p>' + (picot_aio_optimizer.strings.batch_complete || 'Batch generation complete! All suggestions cleared.') + '</p></div>';
                getAllResultPanels().html(html);
            }
        });
    }

    function fetchPostHistory(autoLoadLatest) {
        var postId = getEditorPostId();
        if (!postId) return;
        
        autoLoadLatest = (autoLoadLatest === true);

        // Collect ALL active result containers (doc panel, sidebar panel, classic editor)
        var getAllPanels = function() {
            var panels = $();
            var docPanel     = $('#picot_aio_optimizer-result-panel');
            var sidebarPanel = $('#picot_aio_optimizer-result-panel-sidebar');
            var classicPanel = $('#picot_aio_optimizer-classic-results');
            if (docPanel.length)     panels = panels.add(docPanel);
            if (sidebarPanel.length) panels = panels.add(sidebarPanel);
            if (classicPanel.length) panels = panels.add(classicPanel);
            return panels;
        };

        var allPanels = getAllPanels();
        if (allPanels.length === 0) {
            debugLog('Result div not found', 'warn');
            return;
        }

        allPanels.html('<div class="notice notice-info"><p>' + (picot_aio_optimizer.strings.loading_history || 'Loading history...') + '</p></div>');

        $.ajax({
            url: picot_aio_optimizer.rest_url_history,
            type: "GET",
            data: { post_id: postId, limit: 10 },
            beforeSend: function ( xhr ) {
                xhr.setRequestHeader( 'X-WP-Nonce', picot_aio_optimizer.rest_nonce );
            },
            success: function ( response ) {
                if (response.success && response.data && response.data.length > 0) {
                    window.PicotAioOptimizer.currentHistory = response.data;
                    
                    if (autoLoadLatest) {
                        // Automatically show the most recent result
                        window.PicotAioOptimizer.restoreHistoryItem(response.data[0].id);
                    } else {
                        // User clicked the button, show the list in all panels
                        var html = '<h4>' + (picot_aio_optimizer.strings.history_title || 'History') + '</h4><ul style="border-top:1px solid #eee; padding-top:10px;">';
                        response.data.forEach(function(log) {
                             var logId = parseInt(log.id, 10) || 0;
                             html += '<li style="margin-bottom:10px; font-size:12px;">';
                             html += '<strong>[' + escapeHtml(log.created_at) + ']</strong><br>';
                             html += '<button type="button" class="button button-small picot-history-restore" data-log-id="' + logId + '">' + (picot_aio_optimizer.strings.show_btn || 'Show') + '</button> ';
                             html += '<button type="button" class="button button-small picot-history-expand" data-log-id="' + logId + '">' + (picot_aio_optimizer.strings.expand_view || '拡大表示') + '</button>';
                             html += '</li>';
                        });
                        html += '</ul>';
                        // Re-collect panels at write time (React may have re-rendered)
                        getAllPanels().html(html);
                    }
                } else {
                    if (!autoLoadLatest) {
                        getAllPanels().html('<div class="notice notice-warning"><p>' + (picot_aio_optimizer.strings.no_history || 'No history found.') + '</p></div>');
                    }
                }
            },
            error: function(xhr) {
                getAllPanels().html('<div class="notice notice-error"><p>' + (picot_aio_optimizer.strings.load_history_error || 'Failed to load history.') + '</p></div>');
            }
        });
    }

    // Expose helper to global window for onclick handlers
    window.PicotAioOptimizer = window.PicotAioOptimizer || {};
    window.PicotAioOptimizer.openHistoryExpand = function(id) {
        if (!window.PicotAioOptimizer.currentHistory) {
            return;
        }
        var item = window.PicotAioOptimizer.currentHistory.find(function(h) { return h.id == id; });
        if (item) {
            openHistoryDetailModal(item);
        }
    };

    window.PicotAioOptimizer.restoreHistoryItem = function(id, isClassic) {
        if (!window.PicotAioOptimizer.currentHistory) return;
        var item = window.PicotAioOptimizer.currentHistory.find(function(h) { return h.id == id; });
        if (item) {
            try {
                var advice = item.advice_result;
                if (typeof advice === 'string') {
                    // Strip potential Markdown wrapping before checking content
                    var cleanAdvice = advice.trim().replace(/^```json\s*/i, '').replace(/^```\s*/i, '').replace(/\s*```$/i, '');
                    
                    if (cleanAdvice.startsWith('{') || cleanAdvice.startsWith('[')) {
                        try {
                            var parsed = JSON.parse(cleanAdvice);
                            displayResultsInternal(parsed, isClassic ? $('#picot_aio_optimizer-classic-results') : undefined);
                            return;
                        } catch(e) {
                            // Fall through to raw display
                        }
                    }
                    displayResultsInternal(advice, isClassic ? $('#picot_aio_optimizer-classic-results') : undefined);
                } else {
                    displayResultsInternal(advice, isClassic ? $('#picot_aio_optimizer-classic-results') : undefined);
                }
            } catch(e) {
                debugLog('Failed to restore history item: ' + e.message, 'error');
            }
        }
    };

    // Clipboard helper
    window.PicotAioOptimizer.copyToClipboard = function(text) {
        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(text).then(function() {
                alert(picot_aio_optimizer.strings.copied || 'Copied!');
            }).catch(function(err) {
                debugLog('Copy failed: ' + err.message, 'error');
                fallbackCopy(text);
            });
        } else {
            fallbackCopy(text);
        }
    };

    // インライン onclick を使わず、データ属性から安全にコピーする。
    $(document).on('click', '.picot_aio_optimizer-copy-btn', function () {
        window.PicotAioOptimizer.copyToClipboard($(this).attr('data-copy-text') || '');
    });

    $(document).on('click', '.picot-history-restore', function () {
        var logId = parseInt($(this).attr('data-log-id'), 10) || 0;
        var isClassic = $(this).attr('data-classic') === '1';
        window.PicotAioOptimizer.restoreHistoryItem(logId, isClassic);
    });

    $(document).on('click', '.picot-history-expand', function () {
        var logId = parseInt($(this).attr('data-log-id'), 10) || 0;
        window.PicotAioOptimizer.openHistoryExpand(logId);
    });

    $(document).on('click', '.picot-clear-results', function () {
        window.location.reload();
    });

    function fallbackCopy(text) {
        var textarea = document.createElement('textarea');
        textarea.value = text;
        textarea.style.position = 'fixed';
        textarea.style.opacity = 0;
        document.body.appendChild(textarea);
        textarea.select();
        try {
            document.execCommand('copy');
            alert(picot_aio_optimizer.strings.copied || 'Copied!');
        } catch (err) {
            alert(picot_aio_optimizer.strings.copy_failed || 'Copy failed. Please copy manually.');
        }
        document.body.removeChild(textarea);
    }

    function analyzeContent() {
 //         console.log('Picot AIO Optimizer: analyzeContent() called');
        var postContent = getEditorContent();
        var postId = requireEditorPostId();
        if (!postId) {
            return;
        }

        if (!postContent) {
             var diagMsg = 'Diagnostics:\n';
             diagMsg += '- Classic Editor mode: ' + (isClassicEditorMode() ? 'Yes' : 'No') + '\n';
             diagMsg += '- Gutenberg available: ' + (hasGutenbergEditor() ? 'Yes' : 'No') + '\n';
             diagMsg += '- Classic Editor available: ' + (typeof tinymce !== 'undefined' && tinymce.activeEditor ? 'Yes' : 'No') + '\n';
             diagMsg += 'If you have text in the editor, your page builder might not be supported.';
             
             alert(picot_aio_optimizer.strings.no_content + '\n\n' + diagMsg);
             return;
        }

        var updatePanels = function(htmlStr) { getAllResultPanels().html(htmlStr); };

        updatePanels('<div class="notice notice-info"><p>' + picot_aio_optimizer.strings.analyzing + '</p></div>');

        var formData = "content=" + encodeURIComponent(postContent || '') + "&post_id=" + postId;

        showOverlay(picot_aio_optimizer.strings.analyzing || 'Analyzing...');
        
        $.ajax({
            url: picot_aio_optimizer.rest_url_analyze,
            type: "POST",
            contentType: 'application/x-www-form-urlencoded; charset=UTF-8',
            beforeSend: function ( xhr ) {
                xhr.setRequestHeader( 'X-WP-Nonce', picot_aio_optimizer.rest_nonce );
            },
            data: formData,
            success: function (response) {
                if (response.success) {
                    displayResultsInternal(response.data);
                } else {
                     var msg = response.data || picot_aio_optimizer.strings.error;
                     updatePanels('<div class="notice notice-error"><p>' + msg + '</p></div>');
                }
            },
            error: function (xhr) {
                updatePanels('<div class="notice notice-error"><p>' + escapeHtml(handleAjaxError(xhr, 'Analysis')) + '</p></div>');
            },
            complete: function() {
                hideOverlay();
            }
        });
    }

    function parseAdviceResult(adviceResult) {
        if (!adviceResult) {
            return null;
        }
        var raw = typeof adviceResult === 'string' ? adviceResult : JSON.stringify(adviceResult);
        var clean = raw.trim().replace(/^```json\s*/i, '').replace(/^```\s*/i, '').replace(/\s*```$/i, '');
        try {
            return JSON.parse(clean);
        } catch (e) {
            return null;
        }
    }

    function normalizeAnalysisResponse(response) {
        if (!response || typeof response !== 'object') {
            return response;
        }
        var normalized = $.extend({}, response);
        if (!normalized.summary && normalized.summary_ja) {
            normalized.summary = normalized.summary_ja;
        }
        return normalized;
    }

    function getPostEditUrl(postId) {
        if (!postId || !picot_aio_optimizer.admin_url) {
            return '#';
        }
        return picot_aio_optimizer.admin_url + 'post.php?post=' + encodeURIComponent(postId) + '&action=edit';
    }

    function closeHistoryModal() {
        $('#picot_aio_optimizer-modal').hide().attr('aria-hidden', 'true');
        $('body').removeClass('picot-aio-optimizer-modal-open');
    }

    function ensureHistoryModal() {
        var $modal = $('#picot_aio_optimizer-modal');
        if ($modal.length) {
            $modal.addClass('picot-aio-optimizer-modal');
            return;
        }

        var titleDefault = picot_aio_optimizer.strings.analysis_result_title || 'Analysis Result';
        $modal = $(
            '<div id="picot_aio_optimizer-modal" class="picot-aio-optimizer-modal" style="display:none;" aria-hidden="true">' +
            '<div class="picot-aio-optimizer-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="picot_aio_optimizer-modal-title">' +
            '<div class="picot-aio-optimizer-modal__header">' +
            '<span id="picot_aio_optimizer-modal-close" class="picot-aio-optimizer-modal__close" role="button" tabindex="0">&times;</span>' +
            '<h3 id="picot_aio_optimizer-modal-title">' + escapeHtml(titleDefault) + '</h3>' +
            '</div>' +
            '<div id="picot_aio_optimizer-modal-content" class="picot-aio-optimizer-modal__body"></div>' +
            '</div></div>'
        );
        $('body').append($modal);

        $modal.on('click', function(e) {
            if ($(e.target).is('#picot_aio_optimizer-modal')) {
                closeHistoryModal();
            }
        });
        $('#picot_aio_optimizer-modal-close').on('click', closeHistoryModal);
        $(document).on('keydown.picotHistoryModal', function(e) {
            if (e.key === 'Escape' && $('#picot_aio_optimizer-modal').is(':visible')) {
                closeHistoryModal();
            }
        });
    }

    function openHistoryDetailModal(log) {
        if (!log) {
            return;
        }

        ensureHistoryModal();

        var parsed = parseAdviceResult(log.advice_result);
        var resultHtml = '';

        if (parsed) {
            resultHtml = buildAnalysisResultHtml(normalizeAnalysisResponse(parsed), {
                includeHistory: false,
                includeClearButton: false,
                showCopyButtons: true
            });
        } else {
            resultHtml = '<div class="notice notice-warning"><p>' + (picot_aio_optimizer.strings.format_warning || 'AI response format was unexpected.') + '</p></div>';
            resultHtml += '<pre style="white-space:pre-wrap;">' + escapeHtml(log.advice_result) + '</pre>';
        }

        $('#picot_aio_optimizer-modal-title').text(
            (picot_aio_optimizer.strings.analysis_result_title || 'Analysis Result') +
            ' - ' + (picot_aio_optimizer.strings.save_date || '') + new Date(log.created_at).toLocaleString()
        );
        $('#picot_aio_optimizer-modal-content').html(resultHtml);
        $('#picot_aio_optimizer-modal').show().attr('aria-hidden', 'false');
        $('body').addClass('picot-aio-optimizer-modal-open');
    }

    function buildAnalysisResultHtml(response, options) {
        options = options || {};
        var includeHistory = !!options.includeHistory;
        var includeClearButton = options.includeClearButton !== false;
        var showCopyButtons = options.showCopyButtons !== false;
        var isClassicTarget = !!options.isClassicTarget;

        if (!response) {
            return '<div class="notice notice-error"><p>' + (picot_aio_optimizer.strings.error || 'No analysis data found.') + '</p></div>';
        }

        if (typeof response === 'string' || !response.summary) {
            var rawText = (typeof response === 'string') ? response : JSON.stringify(response, null, 2);
            var htmlFallback = '<div class="notice notice-warning" style="padding:15px;">';
            htmlFallback += '<h3 style="margin-top:0;">' + (picot_aio_optimizer.strings.analysis_result_title || 'Analysis Result') + '</h3>';
            htmlFallback += '<p><strong>⚠️ ' + (picot_aio_optimizer.strings.format_warning || 'AI response format was unexpected. Displaying as text.') + '</strong></p>';
            htmlFallback += '<div style="background:#fff; padding:15px; border:1px solid #ddd; border-radius:4px;">' + renderMarkdown(rawText) + '</div>';
            if (includeClearButton) {
                htmlFallback += '<div style="margin-top:15px; border-top:1px solid #ccd0d4; padding-top:10px;">';
                htmlFallback += '<button type="button" class="button button-secondary picot-clear-results">' + (picot_aio_optimizer.strings.clear_results_btn || 'Clear Results') + '</button>';
                htmlFallback += '</div>';
            }
            htmlFallback += '</div>';
            return htmlFallback;
        }

        function renderAnalysisSection(title, items, borderColor) {
            if (!items || (Array.isArray(items) && items.length === 0)) {
                return '';
            }
            var sectionHtml = '<div style="margin-bottom:20px; padding-left:10px; border-left:3px solid ' + borderColor + ';">';
            sectionHtml += '<strong style="display:block; margin-bottom:5px; font-size:13px; color:#2271b1;">' + title + '</strong>';

            if (Array.isArray(items)) {
                sectionHtml += '<ul style="margin:0 0 0 16px; list-style:disc; padding:0; line-height:1.5;">';
                items.forEach(function(item) {
                    sectionHtml += '<li style="margin-bottom:6px;">' + renderMarkdown(item) + '</li>';
                });
                sectionHtml += '</ul>';
            } else {
                sectionHtml += '<div style="line-height:1.5;">' + renderMarkdown(items) + '</div>';
            }
            sectionHtml += '</div>';
            return sectionHtml;
        }

        var html = '<div style="font-size:13px; color:#1d2327;">';
        html += '<h3 style="margin-top:0; margin-bottom:15px; padding-bottom:8px; border-bottom:1px solid #ccd0d4; font-size:14px;">' + (picot_aio_optimizer.strings.analysis_result_title || 'Analysis Result') + '</h3>';
        html += '<div style="margin-bottom:20px;">';
        html += '<strong style="display:block; margin-bottom:5px; font-size:13px;">' + (picot_aio_optimizer.strings.label_summary || 'Summary') + '</strong>';
        html += '<div style="line-height:1.5;">' + renderMarkdown(response.summary) + '</div>';
        html += '</div>';
        html += renderAnalysisSection(picot_aio_optimizer.strings.label_structure || 'Structure Analysis', response.structure_analysis, '#2271b1');
        html += renderAnalysisSection(picot_aio_optimizer.strings.label_content_advice || 'Content Advice', response.content_advice, '#d63638');
        html += renderAnalysisSection(picot_aio_optimizer.strings.label_seo_advice || 'SEO Advice', response.seo_advice, '#008a20');
        html += renderAnalysisSection(picot_aio_optimizer.strings.label_aio_advice || 'AIO Advice', response.aio_advice, '#dba617');
        html += renderAnalysisSection(picot_aio_optimizer.strings.label_recommended || 'Recommended Content', response.recommended_content, '#8224e3');

        if (response.seo_title_ideas && response.seo_title_ideas.length > 0) {
            html += '<div style="margin-bottom:20px; padding-left:10px; border-left:3px solid #50575e;">';
            html += '<strong style="display:block; margin-bottom:5px; font-size:13px; color:#50575e;">' + (picot_aio_optimizer.strings.label_titles || 'SEO Title Ideas') + '</strong>';
            html += '<ul style="margin:0 0 0 16px; list-style:none; padding:0; line-height:1.5;">';
            response.seo_title_ideas.forEach(function(title) {
                html += '<li style="margin-bottom:8px;">' + renderMarkdown(title);
                if (showCopyButtons) {
                    html += ' <button type="button" class="button button-small picot_aio_optimizer-copy-btn" data-copy-text="' + escapeHtml(title) + '" style="margin-left:5px; vertical-align:middle;">' + (picot_aio_optimizer.strings.copy_btn || 'Copy') + '</button>';
                }
                html += '</li>';
            });
            html += '</ul></div>';
        }

        if (response.meta_description_suggestions && response.meta_description_suggestions.length > 0) {
            html += '<div style="margin-bottom:20px; padding-left:10px; border-left:3px solid #00acc1;">';
            html += '<strong style="display:block; margin-bottom:5px; font-size:13px; color:#00acc1;">' + (picot_aio_optimizer.strings.label_meta || 'Meta Description Suggestions') + '</strong>';
            html += '<ul style="margin:0 0 0 16px; list-style:none; padding:0; line-height:1.5;">';
            response.meta_description_suggestions.forEach(function(desc) {
                html += '<li style="margin-bottom:8px;">' + renderMarkdown(desc);
                if (showCopyButtons) {
                    html += ' <button type="button" class="button button-small picot_aio_optimizer-copy-btn" data-copy-text="' + escapeHtml(desc) + '" style="margin-left:5px; vertical-align:middle;">' + (picot_aio_optimizer.strings.copy_btn || 'Copy') + '</button>';
                }
                html += '</li>';
            });
            html += '</ul></div>';
        }

        if (includeHistory && window.PicotAioOptimizer.currentHistory && window.PicotAioOptimizer.currentHistory.length > 0) {
            html += '<div style="margin-top:30px; border-top:1px solid #ccd0d4; padding-top:15px;">';
            html += '<h4 style="margin:0 0 10px 0; font-size:13px; color:#1d2327;">' + (picot_aio_optimizer.strings.history_title || 'Analysis History') + '</h4>';
            html += '<ul style="margin:0; padding:0; list-style:none;">';
            window.PicotAioOptimizer.currentHistory.forEach(function(log) {
                var logId = parseInt(log.id, 10) || 0;
                html += '<li style="margin-bottom:8px; font-size:12px; display:flex; justify-content:space-between; align-items:center;">';
                html += '<span>' + escapeHtml(String(log.created_at || '').split(' ')[0]) + '</span>';
                html += '<button type="button" class="button button-small picot-history-restore" data-log-id="' + logId + '" data-classic="' + (isClassicTarget ? '1' : '0') + '">' + (picot_aio_optimizer.strings.show_btn || 'Show') + '</button> ';
                html += '<button type="button" class="button button-small picot-history-expand" data-log-id="' + logId + '">' + (picot_aio_optimizer.strings.expand_view || '拡大表示') + '</button>';
                html += '</li>';
            });
            html += '</ul></div>';
        }

        if (includeClearButton) {
            html += '<div style="margin-top:20px; padding-top:15px;">';
            html += '<button type="button" class="button button-secondary picot-clear-results">' + (picot_aio_optimizer.strings.clear_results_btn || 'Clear Results') + '</button>';
            html += '</div>';
        }

        html += '</div>';
        return html;
    }

    function displayResultsInternal(response, targetDiv) {
        var renderTargets = [];
        if (targetDiv) {
            renderTargets.push(targetDiv);
        } else {
            getAllResultPanels().each(function() { renderTargets.push($(this)); });
        }

        var normalized = normalizeAnalysisResponse(response);
        var html = buildAnalysisResultHtml(normalized, {
            includeHistory: !!(window.PicotAioOptimizer.currentHistory && window.PicotAioOptimizer.currentHistory.length > 0),
            includeClearButton: true,
            showCopyButtons: true,
            isClassicTarget: !!targetDiv
        });

        renderTargets.forEach(function(target) {
            target.html(html);
        });
    }

    function renderMarkdown(text) {
        if (!text) return '';
        
        // 1. Escape HTML bits to avoid XSS
        var html = text.toString().replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;");

        // 2. Block Elements (Headers)
        html = html.replace(/^### (.*?)$/gm, '<h3>$1</h3>');
        html = html.replace(/^## (.*?)$/gm, '<h2>$1</h2>');
        html = html.replace(/^# (.*?)$/gm, '<h1>$1</h1>');

        // 3. Inline Elements
        html = html.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>'); // Bold
        html = html.replace(/\*(.*?)\*/g, '<em>$1</em>'); // Italic
        html = html.replace(/`(.*?)`/g, '<code style="background:#eee;padding:2px 4px;border-radius:3px;">$1</code>'); // Code

        // 4. Lists (Support - and *)
        html = html.replace(/^\s*[\-\*]\s+(.*?)$/gm, '<li>$1</li>');

        // 5. Short line breaks
        html = html.replace(/\n/g, '<br>');
        
        return html;
    }
    
    function escapeHtml(text) {
        if (!text) return '';
        return text.toString().replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/'/g, "&#039;");
    }

    // ==========================================
    // CLASSIC EDITOR SUPPORT
    // ==========================================
    
    // Check if we're in Classic Editor (no block editor available)
    $(document).ready(function() {
        if (isClassicEditorMode()) {
            setTimeout(function() {
                fetchPostHistory(true);
                loadSavedImageSuggestions();
            }, 1000);
        }

        // Classic Editor: Analyze button
        $('#picot_aio_optimizer-classic-analyze').on('click', function() {
            classicAnalyzeContent();
        });

        // Classic Editor: Rewrite button
        $('#picot_aio_optimizer-classic-rewrite').off('click').on('click', function() {
            classicTriggerRewrite();
        });

        // Classic Editor: Discover Images button
        $('#picot_aio_optimizer-classic-discover-images').on('click', function() {
            classicDiscoverImages();
        });
    });

    /**
     * Classic Editor Rewrite Trigger
     */
    function classicTriggerRewrite() {
        var instructions = $('#picot_aio_optimizer-classic-instructions').val() || '';
        triggerRewrite(instructions);
    }

    // Classic Editor: Get content from TinyMCE or textarea
    function getClassicEditorContent() {
        var content = '';
        
        // Check if TinyMCE is active
        if (typeof tinymce !== 'undefined' && tinymce.activeEditor && !tinymce.activeEditor.isHidden()) {
            content = tinymce.activeEditor.getContent();
        } else {
            // Fall back to textarea
            content = $('#content').val() || '';
        }
        
        return content;
    }

    // Classic Editor: Set content to TinyMCE or textarea
    function setClassicEditorContent(content) {
        // Check if TinyMCE is active
        if (typeof tinymce !== 'undefined' && tinymce.activeEditor && !tinymce.activeEditor.isHidden()) {
            tinymce.activeEditor.setContent(content);
        } else {
            // Fall back to textarea
            $('#content').val(content);
        }
    }

    // Classic Editor: Get title
    function getClassicEditorTitle() {
        return $('#title').val() || '';
    }

    function whenClassicEditorReady(callback) {
        if (!isClassicEditorMode()) {
            callback();
            return;
        }

        if (typeof tinymce !== 'undefined' && tinymce.activeEditor && !tinymce.activeEditor.isHidden()) {
            callback();
            return;
        }

        if (typeof tinymce !== 'undefined' && typeof tinymce.on === 'function') {
            var completed = false;
            var runOnce = function() {
                if (completed) {
                    return;
                }
                completed = true;
                callback();
            };

            tinymce.on('AddEditor', function() {
                setTimeout(runOnce, 100);
            });
            setTimeout(runOnce, 1500);
            return;
        }

        callback();
    }

    // Classic Editor: Analyze content
    function classicAnalyzeContent() {
        analyzeContent();
    }

    // Classic Editor: Discover image opportunities
    function classicDiscoverImages() {
        discoverImagePrompts();
    }

    /**
     * Settings Page Logic
     */
    function initSettingsPage() {
        var $fetchBtn = $('#picot_aio_optimizer_fetch_models');
        if ($fetchBtn.length > 0) {
            $fetchBtn.on('click', function() {
                var $status = $('#picot_aio_optimizer_fetch_status');
                $fetchBtn.prop('disabled', true);
                $status.text(picot_aio_optimizer.strings.fetching || picot_aio_optimizer.strings.fetching_models || 'Fetching...');
                
                $.ajax({
                    url: picot_aio_optimizer.rest_url_models,
                    type: 'POST',
                    beforeSend: function(xhr) {
                        xhr.setRequestHeader('X-WP-Nonce', picot_aio_optimizer.rest_nonce);
                    },
                    success: function(response) {
                        if (response.success && response.data) {
                            var currentModel = $('#picot_aio_optimizer_model').val();
                            var currentImgModel = $('#picot_aio_optimizer_image_model').val();
                            var $modelSelect = $('#picot_aio_optimizer_model');
                            var $imgModelSelect = $('#picot_aio_optimizer_image_model');
                            var textModels = response.data.text_models || {};
                            var imageModels = response.data.image_models || {};

                            $modelSelect.empty();
                            $imgModelSelect.empty();

                            $.each(textModels, function(modelId, displayName) {
                                $modelSelect.append($('<option>', { value: modelId, text: displayName }));
                            });
                            $.each(imageModels, function(modelId, displayName) {
                                $imgModelSelect.append($('<option>', { value: modelId, text: displayName }));
                            });

                            if (currentModel) {
                                $modelSelect.val(currentModel);
                            }
                            if (currentImgModel) {
                                $imgModelSelect.val(currentImgModel);
                            }
                            $status.text(picot_aio_optimizer.strings.fetch_models_done || 'Done');
                        } else {
                            $status.text(picot_aio_optimizer.strings.fetch_models_error || 'Error');
                        }
                    },
                    error: function() {
                        $status.text(picot_aio_optimizer.strings.error || 'Error');
                    },
                    complete: function() {
                        $fetchBtn.prop('disabled', false);
                    }
                });
            });
        }

        $('#picot_aio_optimizer_enable_image_gen').on('change', function() {
            if ($(this).is(':checked')) {
                $('#picot_aio_optimizer_image_style_row').show();
                $('#picot_aio_optimizer_image_model_row').show();
                $('#picot_aio_optimizer_common_image_prompt_row').show();
            } else {
                $('#picot_aio_optimizer_image_style_row').hide();
                $('#picot_aio_optimizer_image_model_row').hide();
                $('#picot_aio_optimizer_common_image_prompt_row').hide();
            }
        });

        // Toggle image generation UI when paid/free plan changes (before save).
        (function syncImageGenWithApiPlan() {
            var $plan = $('#picot_aio_optimizer_api_plan');
            var $checkbox = $('#picot_aio_optimizer_enable_image_gen');
            if (!$plan.length || !$checkbox.length) {
                return;
            }

            var $planFreeNotice = $('#picot_aio_optimizer_api_plan_free_notice');
            var $imageFreeNotice = $('#picot_aio_optimizer_image_gen_free_notice');
            var $imageRows = $('#picot_aio_optimizer_image_style_row, #picot_aio_optimizer_image_model_row, #picot_aio_optimizer_common_image_prompt_row');
            var rememberedChecked = $checkbox.data('saved-checked') === 1 || $checkbox.data('saved-checked') === '1';

            var applyPlan = function () {
                var isPaid = $plan.val() === 'paid';
                var $editable = $('#picot_aio_optimizer_image_gen_editable');

                $planFreeNotice.toggle(!isPaid);
                $imageFreeNotice.toggle(!isPaid);

                if (!isPaid) {
                    rememberedChecked = $checkbox.is(':checked') || rememberedChecked;
                    $checkbox.prop('checked', false).prop('disabled', true);
                    $editable.remove();
                    $imageRows.hide();
                    return;
                }

                $checkbox.prop('disabled', false);
                if (!$editable.length) {
                    $checkbox.before('<input type="hidden" name="picot_aio_optimizer_image_gen_editable" id="picot_aio_optimizer_image_gen_editable" value="1">');
                }
                if (rememberedChecked) {
                    $checkbox.prop('checked', true);
                    $imageRows.show();
                } else {
                    $checkbox.prop('checked', false);
                    $imageRows.hide();
                }
            };

            $checkbox.on('change', function () {
                if ($plan.val() === 'paid') {
                    rememberedChecked = $checkbox.is(':checked');
                }
            });

            $plan.on('change', applyPlan);
            applyPlan();
        })();

        // Global History List (Settings Page)
        var $historyList = $('#picot_aio_optimizer-history-list');
        if ($historyList.length > 0) {
            var historyData = [];
            
            var fetchGlobalHistory = function() {
                $.ajax({
                    url: picot_aio_optimizer.rest_url_history,
                    type: 'GET',
                    beforeSend: function(xhr) {
                        xhr.setRequestHeader('X-WP-Nonce', picot_aio_optimizer.rest_nonce);
                    },
                    success: function(response) {
                        $historyList.empty();
                        if (response.success && response.data) {
                            historyData = response.data;
                            $.each(response.data, function(i, log) {
                                var parsed = parseAdviceResult(log.advice_result);
                                var summary = picot_aio_optimizer.strings.no_data || '(No data)';
                                if (parsed && (parsed.summary || parsed.summary_ja)) {
                                    summary = parsed.summary_ja || parsed.summary;
                                }

                                var dateStr = new Date(log.created_at).toLocaleString();
                                var editUrl = getPostEditUrl(log.post_id);
                                var row = $('<div class="picot-history-item" data-index="' + i + '">' +
                                    '<div class="col-date">' + escapeHtml(dateStr) + '</div>' +
                                    '<div class="col-id"><a href="' + escapeHtml(editUrl) + '" class="picot-history-post-link">' + escapeHtml(String(log.post_id)) + '</a></div>' +
                                    '<div class="col-summary">' + escapeHtml(summary) + '</div>' +
                                    '<div class="col-action picot-history-actions"><button type="button" class="button expand-view">' + (picot_aio_optimizer.strings.expand_view || '拡大表示') + '</button></div>' +
                                    '</div>');
                                $historyList.append(row);
                            });
                        } else {
                            $historyList.html('<p style="padding:20px; text-align:center;">' + (picot_aio_optimizer.strings.no_history || '履歴が見つかりません。') + '</p>');
                        }
                    }
                });
            };

            fetchGlobalHistory();

            ensureHistoryModal();

            $(document).on('click', '.picot-history-item .expand-view', function(e) {
                e.preventDefault();
                var $row = $(this).closest('.picot-history-item');
                var idx = $row.data('index');
                var log = historyData[idx];
                if (log) {
                    openHistoryDetailModal(log);
                }
            });

            $('#picot_aio_optimizer-modal-close').off('click.picotHistory').on('click.picotHistory', closeHistoryModal);
        }
    }

})( jQuery );
