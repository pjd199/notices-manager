import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import { PanelBody, SelectControl, TextControl, ExternalLink } from '@wordpress/components';
import { useSelect } from '@wordpress/data';
import { __ } from '@wordpress/i18n';
import { dateI18n } from '@wordpress/date';

const DATE_FORMATS = [
    { label: 'l jS F Y (Tuesday 31st January 2026)', value: 'l jS F Y' },
    { label: 'F j, Y (January 31, 2026)', value: 'F j, Y' },
    { label: 'Y-m-d (2026-01-31)', value: 'Y-m-d' },
    { label: 'm/d/Y (01/31/2026)', value: 'm/d/Y' },
    { label: 'd/m/Y (31/01/2026)', value: 'd/m/Y' },
    { label: 'Custom', value: 'custom' },
];

const TIME_FORMATS = [
    { label: 'None', value: 'none' },
    { label: 'g:i a (7:15 pm)', value: 'g:i a' },
    { label: 'g:i A (7:15 PM)', value: 'g:i A' },
    { label: 'H:i (19:15)', value: 'H:i' },
    { label: 'h:i a (07:15 pm)', value: 'h:i a' },
    { label: 'Custom', value: 'custom' },
];

export default function Edit({ attributes, setAttributes, context }) {
    const { dateFormat, customDateFormat, timeFormat, customTimeFormat } = attributes;

    const postId = context['postId'];

    const eventDate = useSelect((select) => {
        if (!postId) return null;
        const post = select('core').getEntityRecord('postType', 'post', postId);
        return post?.meta?.event_start_time ?? null;
    }, [postId]);

    const blockProps = useBlockProps();

    const activeDate = dateFormat === 'custom' ? customDateFormat : dateFormat;
    let activeTime = '';
    if (timeFormat === 'custom') {
        activeTime = customTimeFormat;
    } else if (timeFormat !== 'none') {
        activeTime = timeFormat;
    }
    const finalFormat = activeTime ? `${activeDate}, ${activeTime}` : activeDate;

    const handleDateChange = (val) => {
        const newAttrs = { dateFormat: val };

        // If they chose a preset (not 'custom'), sync it to the custom field
        if (val !== 'custom') {
            newAttrs.customDateFormat = val;
        }

        setAttributes(newAttrs);
    };

    const handleTimeChange = (val) => {
        const newAttrs = { timeFormat: val };

        // If they chose a preset (not 'custom' or 'none'), sync it
        if (val !== 'custom' && val !== 'none') {
            newAttrs.customTimeFormat = val;
        }

        setAttributes(newAttrs);
    };

    return (
        <>
            <InspectorControls>
                <PanelBody title={__('Date & Time Format')}>

                    <SelectControl
                        label={__('Date Format')}
                        value={dateFormat}
                        options={DATE_FORMATS}
                        onChange={handleDateChange}
                    />
                    {dateFormat === 'custom' && (
                        <TextControl
                            label={__('Custom Date Format')}
                            value={customDateFormat}
                            onChange={(val) => setAttributes({ customDateFormat: val })}
                            help={__('Use PHP date format strings, e.g. d/m/Y')}
                        />
                    )}

                    <SelectControl
                        label={__('Time Format')}
                        value={timeFormat}
                        options={TIME_FORMATS}
                        onChange={handleTimeChange}
                    />
                    {timeFormat === 'custom' && (
                        <TextControl
                            label={__('Custom Time Format')}
                            value={customTimeFormat}
                            onChange={(val) => setAttributes({ customTimeFormat: val })}
                            help={__('Use PHP date format strings, e.g. H:i')}
                        />
                    )}

                    <ExternalLink
                        href="https://wordpress.org/documentation/article/customize-date-and-time-format/"
                        style={{ display: 'block', marginTop: '12px', fontSize: '12px' }}
                    >
                        {__('Date and Time Format documentation')}
                    </ExternalLink>

                </PanelBody>
            </InspectorControls>
            <p {...blockProps}>
                {eventDate
                    ? dateI18n(finalFormat, eventDate)
                    : __('No event date set', 'anm')
                }
            </p>
        </>
    );
}