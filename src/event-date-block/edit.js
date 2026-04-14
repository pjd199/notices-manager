import { useBlockProps, InspectorControls, BlockControls, AlignmentControl } from '@wordpress/block-editor';
import { PanelBody, SelectControl, TextControl, ExternalLink,ToolbarGroup, ToolbarButton, ToggleControl } from '@wordpress/components';
import { useSelect } from '@wordpress/data';
import { __ } from '@wordpress/i18n';
import { dateI18n } from '@wordpress/date';
import { formatBold, formatItalic } from '@wordpress/icons';

const DATE_FORMATS = [
    { label: 'l jS F Y (Tuesday 31st January 2026)', value: 'l jS F Y' },
    { label: 'j F Y (31 January 2026)', value: 'j F Y' },
    { label: 'd/m/Y (31/01/2026)', value: 'd/m/Y' },
    { label: 'd-m-Y (31-01-2026)', value: 'd-m-Y' },
    { label: 'm/d/Y (01/31/2026)', value: 'm/d/Y' },
    { label: 'm-d-Y (01/31/2026)', value: 'm-d-Y' },
    { label: 'Custom', value: 'custom' },
];

const TIME_FORMATS = [
    { label: 'None', value: 'none' },
    { label: 'g:i a (7:15 pm)', value: 'g:i a' },
    { label: 'g:i A (7:15 PM)', value: 'g:i A' },
    { label: 'H:i (19:15)', value: 'H:i' },
    { label: 'Custom', value: 'custom' },
];

export default function Edit({ attributes, setAttributes, context }) {
    const { dateFormat, customDateFormat, timeFormat, customTimeFormat, hideZeroMinutes } = attributes;

    const postId = context['postId'];

    const { textAlign, fontWeight, fontStyle } = attributes;

    const eventDate = useSelect((select) => {
        if (!postId) return null;
        const post = select('core').getEntityRecord('postType', 'post', postId);
        return post?.meta?.event_start_time ?? null;
    }, [postId]);

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

    const activeDate = dateFormat === 'custom' ? customDateFormat : dateFormat;
    let activeTime = '';
    if (timeFormat === 'custom') {
        activeTime = customTimeFormat;
    } else if (timeFormat !== 'none') {
        activeTime = timeFormat;
    }

    let displayString = eventDate 
        ? dateI18n(activeTime ? `${activeDate}, ${activeTime}` : activeDate, eventDate) 
     : __('No event date set', 'anm');

    if (eventDate && hideZeroMinutes) {
        // This regex looks for :00 followed by optional space and AM/PM
        displayString = displayString.replace(/:00(\s?(am|pm|AM|PM))?/i, '$1').trim();
    }

    const handleDateChange = (val) => {
    const newAttrs = { dateFormat: val };

    if (val === 'custom') {
            // When switching TO custom, seed it with the current preset value
            // if the custom field is currently empty.
            if (!customDateFormat) {
                newAttrs.customDateFormat = dateFormat;
            }
        } else {
            // If they chose a preset, sync it to the custom field 
            // so the 'Custom' input is ready if they switch back later.
            newAttrs.customDateFormat = val;
        }

        setAttributes(newAttrs);
    };

    const handleTimeChange = (val) => {
        const newAttrs = { timeFormat: val };

        if (val === 'custom') {
            if (!customTimeFormat) {
                // Seed with current preset, but if current is 'none', 
                // give them a sensible default like 'g:i a'
                newAttrs.customTimeFormat = timeFormat === 'none' ? 'g:i a' : timeFormat;
            }
        } else if (val !== 'none') {
            newAttrs.customTimeFormat = val;
        }

        setAttributes(newAttrs);
    };

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
                        label={__('Date Format')}
                        value={dateFormat}
                        options={DATE_FORMATS}
                        onChange={handleDateChange}
                        __nextHasNoMarginBottom={ true }
                        __next40pxDefaultSize={ true }
                    />
                    {dateFormat === 'custom' && (
                        <TextControl
                            label={__('Custom Date Format')}
                            value={customDateFormat}
                            onChange={(val) => setAttributes({ customDateFormat: val })}
                            help={__('Use PHP date format strings, e.g. d/m/Y')}
                            __nextHasNoMarginBottom={ true }
                            __next40pxDefaultSize={ true }
                        />
                    )}

                    <SelectControl
                        label={__('Time Format')}
                        value={timeFormat}
                        options={TIME_FORMATS}
                        onChange={handleTimeChange}
                        __nextHasNoMarginBottom={ true }
                        __next40pxDefaultSize={ true }
                    />
                    {timeFormat === 'custom' && (
                        <TextControl
                            label={__('Custom Time Format')}
                            value={customTimeFormat}
                            onChange={(val) => setAttributes({ customTimeFormat: val })}
                            help={__('Use PHP date format strings, e.g. H:i')}
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