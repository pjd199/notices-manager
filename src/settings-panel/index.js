import { __, sprintf } from '@wordpress/i18n';
import { registerPlugin } from '@wordpress/plugins';
import { PluginDocumentSettingPanel, PluginPrePublishPanel } from '@wordpress/editor';
import {
    DateTimePicker,
    DatePicker,
    PanelRow,
    ToggleControl,
    Modal,
    Button,
    Flex,
    FlexItem
} from '@wordpress/components';
import { useSelect, useDispatch } from '@wordpress/data';
import { useEntityProp } from '@wordpress/core-data';
import { useEffect, useState } from '@wordpress/element';

const NoticeSettingsPanel = () => {
    /**
     * 1. Data Fetching & State
     */
    const postType = useSelect((select) => select('core/editor').getCurrentPostType(), []);
    const { lockPostSaving, unlockPostSaving, editPost } = useDispatch('core/editor');
    const { saveEntityRecord } = useDispatch('core');
    const [meta, setMeta] = useEntityProp('postType', postType, 'meta');
    
    const [isModalOpen, setModalOpen] = useState(false);
    const [hasExpiryDate, setHasExpiryDate] = useState(!!meta?.expiry_date);

    const { selectedCategoryIds, allCategories, selectedTagIds, allTags, isDirty } = useSelect((select) => ({
        selectedCategoryIds: select('core/editor').getEditedPostAttribute('categories') || [],
        allCategories: select('core').getEntityRecords('taxonomy', 'category', { per_page: -1 }) || [],
        selectedTagIds: select('core/editor').getEditedPostAttribute('tags') || [],
        allTags: select('core').getEntityRecords('taxonomy', 'post_tag', { per_page: -1 }) || [],
        isDirty: select('core/editor').isEditedPostDirty(),
    }), []);
    
    /**
     * 2. Helper Logic
     */
    const checkIsCategory = (names) => {
        return allCategories.some(cat => 
            names.includes(cat.name.toLowerCase()) && 
            selectedCategoryIds.includes(cat.id)
        );
    };

    const isEvent = checkIsCategory(['events', 'event']);
    const isTargetCategory = checkIsCategory(window.ANM_SETTINGS.targetCategories);
    const isMissingEventDate = isEvent && !meta?.event_start_time;

    /**
     * 3. Action Handlers
     */
    const updateMeta = (key, val) => {
        setMeta({ 
            ...meta, 
            [key]: val === '' || val === null || val === undefined ? '' : val 
        });
    };

    const handleExpiryToggle = (checked) => {
        setHasExpiryDate(checked);
        const dateVal = checked ? new Date().toISOString().substring(0, 10) : null;
        updateMeta('expiry_date', dateVal);
    };

    const convertPost = async (catName) => {
        const slugPart = catName.toLowerCase();
        const targetTagName = `${slugPart}-full`;
        
        // 1. Find Category
        const targetCat = allCategories.find(c => c.name.toLowerCase() === slugPart || c.slug === slugPart);

        // 2. Find or Create Tag
        let targetTag = allTags.find(t => t.name.toLowerCase() === targetTagName || t.slug === targetTagName);
        
        if (!targetTag) {
            try {
                // This creates the tag in the database immediately
                targetTag = await saveEntityRecord('taxonomy', 'post_tag', { 
                    name: targetTagName,
                    slug: targetTagName 
                });
            } catch (error) {
                console.error(`Could not create tag ${targetTagName}:`, error);
            }
        }

        // 3. Filter out existing notice tags (Swap logic)
        const remainingTagIds = selectedTagIds.filter(tagId => {
            const tagObject = allTags.find(t => t.id === tagId);
            if (!tagObject) return true;
            return !window.ANM_SETTINGS.tagSuffixes.some(suffix => tagObject.slug.endsWith(suffix));
        });

        const updates = {};
        if (targetCat) {
            updates.categories = [...new Set([...selectedCategoryIds, targetCat.id])];
        }
        
        // 4. Update Tags with the newly found/created ID
        if (targetTag && targetTag.id) {
            updates.tags = [...new Set([...remainingTagIds, targetTag.id])];
        }

        if (Object.keys(updates).length > 0) {
            editPost(updates);
        }
    };

    /**
     * 4. Side Effects
     */
    // Sync expiry toggle with meta changes
    useEffect(() => {
        setHasExpiryDate(!!meta?.expiry_date);
    }, [meta?.expiry_date]);

    // Handle saving lock for missing event dates
    useEffect(() => {
        if (isMissingEventDate) {
            lockPostSaving('anm_event_date_missing');
        } else {
            unlockPostSaving('anm_event_date_missing');
        }
    }, [isMissingEventDate, lockPostSaving, unlockPostSaving]);

    // Intercept Back Button Click
    useEffect(() => {
        const backButton = document.querySelector('.edit-post-fullscreen-mode-close');
        if (!backButton) return;

        const handleBackClick = (e) => {
            if (isTargetCategory) {
                e.preventDefault();
                e.stopPropagation();
                setModalOpen(true);
            }
        };

        backButton.addEventListener('click', handleBackClick, true);
        return () => backButton.removeEventListener('click', handleBackClick, true);
    }, [isTargetCategory]);

    /**
     * 5. Render Guards
     */
    if (postType !== 'post' || !meta) return null;

    /**
     * 6. Render UI
     */
    return (
        <>
            <PluginDocumentSettingPanel
                name="anm-settings-panel"
                title={__('Notices Manager', 'anm')}
            >
                {isTargetCategory ? (
                    /* If it IS a target category, show the existing logic 
                    (Event date for Events, Expiry date for others)
                    */
                    <>
                        {isEvent ? (
                            <PanelRow>
                                <div style={{ width: '100%' }}>
                                    <p style={{ fontWeight: '600', color: isMissingEventDate ? '#d63638' : 'inherit' }}>
                                        {__('Event Start', 'anm')} {isMissingEventDate && '*'}
                                    </p>
                                    <DateTimePicker
                                        currentDate={meta.event_start_time || null}
                                        onChange={(val) => updateMeta('event_start_time', val)}
                                        is12Hour={false}
                                    />
                                </div>
                            </PanelRow>
                        ) : (
                            <PanelRow>
                                <div style={{ width: '100%' }}>
                                    <ToggleControl
                                        label={__('Set expiry date', 'anm')}
                                        checked={hasExpiryDate}
                                        onChange={handleExpiryToggle}
                                        __nextHasNoMarginBottom
                                    />
                                    {hasExpiryDate && (
                                        <div style={{ marginTop: '12px' }}>
                                            <DatePicker
                                                currentDate={meta.expiry_date || null}
                                                onChange={(val) => updateMeta('expiry_date', val ? val.substring(0, 10) : null)}
                                            />
                                        </div>
                                    )}
                                </div>
                            </PanelRow>
                        )}
                    </>
                ) : (
                    /* If it is NOT a target category, show the fallback message
                    */
                    <PanelRow>
                        <div style={{ padding: '4px 0' }}>
                            <p style={{ fontSize: '13px', color: '#1e1e1e', marginBottom: '8px' }}>
                                {__('This post is not managed by the Notices Manager.', 'anm')}
                            </p>
                            
                            {/* "Add to" on its own line */}
                            <p style={{ fontSize: '12px', color: '#757575', marginBottom: '4px' }}>
                                {__('Add to:', 'anm')}
                            </p>

                            {/* Links as single words on the next line */}
                            <Flex gap={3} wrap={true} justify="start">
                                {window.ANM_SETTINGS.targetCategories.map((name) => (
                                    <FlexItem key={name}>
                                        <Button 
                                            variant="link" 
                                            onClick={() => convertPost(name)}
                                            style={{ 
                                                height: 'auto', 
                                                padding: '0', 
                                                fontSize: '13px',
                                                textTransform: 'capitalize',
                                                textDecoration: 'none' // Cleaner link look
                                            }}
                                        >
                                            {name}
                                        </Button>
                                    </FlexItem>
                                ))}
                            </Flex>
                        </div>
                    </PanelRow>
                )}
            </PluginDocumentSettingPanel>

            {isMissingEventDate && (
                <PluginPrePublishPanel
                    title={__('Event Date Required', 'anm')}
                    initialOpen={true}
                >
                    <p style={{ color: '#d63638', marginTop: 0 }}>
                        {__('Set an Event Start date before publishing.', 'anm')}
                    </p>
                    <DateTimePicker
                        currentDate={meta.event_start_time || null}
                        onChange={(val) => updateMeta('event_start_time', val)}
                        is12Hour={false}
                    />
                </PluginPrePublishPanel>
            )}

            {isModalOpen && (
                <NavigationModal 
                    onClose={() => setModalOpen(false)} 
                    isDirty={isDirty} 
                />
            )}
        </>
    );
};

/**
 * Extracted Navigation Modal Component
 */
const NavigationModal = ({ onClose, isDirty }) => (
    <Modal
        title={__('Where would you like to go?', 'anm')}
        onRequestClose={onClose}
        className="anm-navigation-modal"
    >
        <p>{__('You are editing a post from the Notices Manager. Where would you like to go?', 'anm')}</p>
        
        {/* Added warning for unsaved changes */}
        {isDirty && (
            <p style={{ color: '#cc1818', fontWeight: '500', backgroundColor: '#fdf3f3', padding: '8px', borderRadius: '4px' }}>
                {__('Warning: You have unsaved changes that will be lost.', 'anm')}
            </p>
        )}

        <Flex justify="flex-end" gap={2}>
            <FlexItem>
                <Button variant="tertiary" onClick={onClose}>
                    {__('Cancel', 'anm')}
                </Button>
            </FlexItem>
            <FlexItem>
                <Button variant="secondary" onClick={() => window.location.href = 'edit.php'}>
                    {__('All Posts', 'anm')}
                </Button>
            </FlexItem>
            <FlexItem>
                <Button variant="primary" onClick={() => window.location.href = 'admin.php?page=notices-manager'}>
                    {__('Notices Manager', 'anm')}
                </Button>
            </FlexItem>
        </Flex>
    </Modal>
);

registerPlugin('anm-settings-plugin', { render: NoticeSettingsPanel });