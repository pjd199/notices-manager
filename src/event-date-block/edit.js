import { useBlockProps, InspectorControls, BlockControls, AlignmentControl } from '@wordpress/block-editor';
import { PanelBody, SelectControl, TextControl, ExternalLink,ToolbarGroup, ToolbarButton, ToggleControl } from '@wordpress/components';
import { useSelect } from '@wordpress/data';
import { __ } from '@wordpress/i18n';
import { dateI18n } from '@wordpress/date';
import { formatBold, formatItalic } from '@wordpress/icons';

const SUGGESTED_FORMATS = [
    // Full Descriptive
    { label: 'Tuesday 31st January, 7:15 pm', value: 'l jS F Y, g:i a' },
    
    // Standard Formal
    { label: 'January 31, 2026', value: 'F j, Y' },
    
    // Clean / Minimalist (Great for cards)
    { label: '31 Jan 2026', value: 'j M Y' },
    
    // No Year (Great for recurring annual events)
    { label: 'Tuesday 31st Jan, 7:15 pm', value: 'l jS M, g:i a' },
    
    // Numeric - UK/International
    { label: '31/01/2026 7:15pm', value: 'd/m/Y g:i a' },
    { label: '31/01/2026 19:15', value: 'd/m/Y H:i' },
    
    // Numeric - US Style
    { label: '01/31/2026', value: 'm/d/Y' },
    
    // Just Time (If they want to pair two blocks)
    { label: '7:15 pm', value: 'g:i a' },
    
    // ISO Style (Technical/Data)
    { label: '2026-01-31', value: 'Y-m-d' },
    
    { label: 'Custom...', value: 'custom' },
];


export default function Edit({ attributes, setAttributes, context }) {
    const { format, isCustomMode, hideZeroMinutes } = attributes;

    const postId = context['postId'];

    const { textAlign, fontWeight, fontStyle } = attributes;

    const eventDate = useSelect((select) => {
        if (!postId) return null;
        const post = select('core').getEntityRecord('postType', 'post', postId);
        return post?.meta?.event_start_time ?? null;
    }, [postId]);

    const selectValue = isCustomMode ? 'custom' : format;

    const blockProps = useBlockProps({
        style: {
            textAlign: textAlign,
            fontWeight: fontWeight || 'normal',
            fontStyle: fontStyle || 'normal',
        },
    });

    const toggleBold = () => {
        setAttributes({ fontWeight: fontWeight === 'bold' ? 'normal' : 'bold' });
    };

    const toggleItalic = () => {
        setAttributes({ fontStyle: fontStyle === 'italic' ? 'normal' : 'italic' });
    };

    let displayString = '';
    let isFormatValid = true;
    if (!eventDate) {
        displayString = __('No event date set', 'anm');
    } else {
        try {
            // Attempt to format. If format is empty or gibberish, 
            // dateI18n might return the raw timestamp or a broken string.
            displayString = dateI18n(format || ' ', eventDate);
            
            // Basic sanity check: if the result is identical to the format string, 
            // it usually means no valid placeholders were found.
            if (format && displayString === format && !/[a-zA-Z]/.test(format)) {
                isFormatValid = false;
            }
        } catch (error) {
            isFormatValid = false;
            displayString = __('Invalid format', 'anm');
        }
    }

    if (eventDate && hideZeroMinutes) {
        displayString = displayString.replace(/:00(\s?(am|pm|AM|PM))?/i, '$1').trim();
    }

    return (
        <>
            <BlockControls group="block">
                <AlignmentControl
                    value={ textAlign }
                    onChange={ ( nextAlign ) => setAttributes( { textAlign: nextAlign } ) }
                />
                <ToolbarGroup>
                    <ToolbarButton
                        icon={ formatBold }
                        title="Bold"
                        onClick={ toggleBold }
                        isActive={ fontWeight === 'bold' }
                    />
                    <ToolbarButton
                        icon={ formatItalic }
                        title="Italic"
                        onClick={ toggleItalic }
                        isActive={ fontStyle === 'italic' }
                    />
                </ToolbarGroup>
            </BlockControls>

            <InspectorControls>
                <PanelBody title={__('Date & Time Format')}>
                    <SelectControl
                        label={__('Format')}
                        value={selectValue}
                        options={SUGGESTED_FORMATS}
                        onChange={(val) => {
                            if (val === 'custom') {
                                // Switch to custom mode but keep current format string
                                setAttributes({ isCustomMode: true });
                            } else {
                                // Switch to preset mode and update the format
                                setAttributes({ 
                                    format: val, 
                                    isCustomMode: false 
                                });
                            }
                        }}
                        __nextHasNoMarginBottom={ true }
                        __next40pxDefaultSize={ true }
                    />
                    {(selectValue === 'custom') && (
                        <TextControl
                            label={__('Custom Format')}
                            value={format}
                            onChange={(val) => setAttributes({ format: val })}
                            help={!isFormatValid ? 
                                <span style={{ color: 'red' }}>{__('Warning: This format may not render correctly.')}</span> : 
                                __('Use PHP date tags (e.g., Y-m-d H:i)')
                                }
                            __nextHasNoMarginBottom={ true }
                            __next40pxDefaultSize={ true }
                        />
                    )}

                    <ToggleControl
                        label={__('Hide :00 on full hours')}
                        help={hideZeroMinutes ? __('e.g., 7pm') : __('e.g., 7:00pm')}
                        checked={hideZeroMinutes}
                        onChange={(val) => setAttributes({ hideZeroMinutes: val })}
                        __nextHasNoMarginBottom={ true }
                        __next40pxDefaultSize={ true }
                    />

                    <ExternalLink
                        href="https://wordpress.org/documentation/article/customize-date-and-time-format/"
                        style={{ display: 'block', marginTop: '12px', fontSize: '12px' }}
                    >
                        {__('Date and Time Format documentation')}
                    </ExternalLink>

                </PanelBody>
            </InspectorControls>
            <p {...blockProps}>
                { eventDate ? displayString : __('No event date set', 'anm') }
            </p>
        </>
    );
}