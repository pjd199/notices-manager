import { __ } from '@wordpress/i18n';
import { registerPlugin } from '@wordpress/plugins';
import { PluginDocumentSettingPanel, PluginPrePublishPanel } from '@wordpress/editor'; 
import { DateTimePicker, DatePicker, PanelRow, ToggleControl } from '@wordpress/components';
import { useSelect, useDispatch } from '@wordpress/data';
import { useEntityProp } from '@wordpress/core-data';
import { useEffect, useState } from '@wordpress/element';

const NoticeSettingsPanel = () => {
    const postType = useSelect( ( select ) => select( 'core/editor' ).getCurrentPostType(), [] );
    const [ meta, setMeta ] = useEntityProp( 'postType', postType, 'meta' );
    const { lockPostSaving, unlockPostSaving } = useDispatch( 'core/editor' );

    const [ hasExpiryDate, setHasExpiryDate ] = useState( !! meta?.expiry_date && meta.expiry_date !== '' );

    useEffect( () => {
        setHasExpiryDate( !! meta?.expiry_date && meta.expiry_date !== '' );
    }, [ meta?.expiry_date ] );

    const { selectedCategoryIds, allCategories } = useSelect( ( select ) => ({
        selectedCategoryIds: select( 'core/editor' ).getEditedPostAttribute( 'categories' ) || [],
        allCategories: select( 'core' ).getEntityRecords( 'taxonomy', 'category', { per_page: -1 } ) || [],
    }), [] );

    const isEvent = allCategories.some( cat => 
        ( cat.name.toLowerCase() === 'events' || cat.name.toLowerCase() === 'event' ) && 
        selectedCategoryIds.includes( cat.id )
    );

    const isMissingEventDate = isEvent && ! meta?.event_start_time;

    useEffect( () => {
        if ( isMissingEventDate ) {
            lockPostSaving( 'anm_event_date_missing' );
        } else {
            unlockPostSaving( 'anm_event_date_missing' );
        }
    }, [ isMissingEventDate, lockPostSaving, unlockPostSaving ] );

    if ( ! meta ) return null;

    const updateMeta = ( key, val ) => {
        const updated = { ...meta };
        if ( val === '' || val === null || val === undefined ) {
            updated[ key ] = '';
        } else {
            updated[ key ] = val;
        }
        setMeta( updated );
    };

    const handleExpiryToggle = ( checked ) => {
        setHasExpiryDate( checked );
        if ( checked ) {
            updateMeta( 'expiry_date', new Date().toISOString().substring( 0, 10 ) );
        } else {
            updateMeta( 'expiry_date', null );
        }
    };

    return (
        <>
            { isMissingEventDate && (
                <PluginPrePublishPanel
                    title={ __( 'Event Date Required', 'anm' ) }
                    initialOpen={ true }
                >
                    <p style={{ color: '#d63638', marginTop: 0 }}>
                        { __( 'Set an Event Start date before publishing.', 'anm' ) }
                    </p>
                    <DateTimePicker
                        currentDate={ meta.event_start_time || null }
                        onChange={ ( val ) => updateMeta( 'event_start_time', val ) }
                        is12Hour={ false }
                    />
                </PluginPrePublishPanel>
            ) }

            <PluginDocumentSettingPanel
                name="anm-settings-panel"
                title={ __( 'Notices Manager', 'anm' ) }
                icon="calendar-alt"
            >
                { isEvent ? (
                    <PanelRow>
                        <div style={{ width: '100%' }}>
                            <p style={{ fontWeight: '600', color: isMissingEventDate ? '#d63638' : 'inherit' }}>
                                { __( 'Event Start', 'anm' ) } { isMissingEventDate && '*' }
                            </p>
                            <DateTimePicker
                                currentDate={ meta.event_start_time || null }
                                onChange={ ( val ) => updateMeta( 'event_start_time', val ) }
                                is12Hour={ false }
                            />
                        </div>
                    </PanelRow>
                ) : (
                    <PanelRow>
                        <div style={{ width: '100%' }}>
                            <ToggleControl
                                label={ __( 'Set expiry date', 'anm' ) }
                                checked={ hasExpiryDate }
                                onChange={ handleExpiryToggle }
                                __nextHasNoMarginBottom={ ! hasExpiryDate }
                            />
                            { hasExpiryDate && (
                                <div style={{ marginTop: '12px' }}>
                                    <DatePicker
                                        currentDate={ meta.expiry_date || null }
                                        onChange={ ( val ) => updateMeta( 'expiry_date', val ? val.substring( 0, 10 ) : null ) }
                                    />
                                </div>
                            ) }
                        </div>
                    </PanelRow>
                ) }
            </PluginDocumentSettingPanel>
        </>
    );
};

registerPlugin( 'anm-settings-plugin', { render: NoticeSettingsPanel } );