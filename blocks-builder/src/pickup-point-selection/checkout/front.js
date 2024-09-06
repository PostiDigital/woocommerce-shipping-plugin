/**
 * System external functions
 **/
import { useEffect, useState } from '@wordpress/element';
import { useDispatch, useSelect } from '@wordpress/data';
import { SelectControl, RadioControl, TextareaControl, Flex, FlexItem, BaseControl } from '@wordpress/components';

/**
 * This plugin external functions and variables
 **/
import { txt } from '../global/text';
import { getActiveShippingRates, getDestination } from '../global/wc';
import { getPluginStaticData, getCurrentMethod, isMethodHavePickups, isMethodPickupRequired, getPickupPoints, getCustomPickupPoints } from '../global/plugin';
import { useDebounce, isValidAddress } from '../global/utils';

/**
 * Exporting functions of this file
 **/
export const Block = ({ checkoutExtensionData, extension }) => {
    /* Declare this function variables */
    const { setExtensionData } = checkoutExtensionData;
    const [ activeRates, setActiveRates ] = useState([]);
    const [ currentData, setCurrentData ] = useState({
        rate: {}
    });
    const [ updateList, setUpdateList ] = useState({
        main: false,
        custom: false
    });
    const [ pickupOptions, setPickupOptions ] = useState([]);
    const validationErrorId = 'pakettikauppa_pickup_point';
    const [ customAddress, setCustomAddress ] = useState('');
    const debouncedAddress = useDebounce(customAddress, 2000);
    const [ customAddressError, setCustomAddressError ] = useState('');
    const [ containerErrorClass, setContainerErrorClass ] = useState('');
    
    /* Get data from WC */
    const { setValidationErrors, clearValidationError } = useDispatch(
        'wc/store/validation'
    );
    const validationError = useSelect((select) => {
        const store = select('wc/store/validation');
        return store.getValidationError(validationErrorId);
    });
    const shippingRates = useSelect((select) => {
        const store = select('wc/store/cart');
        return store.getCartData().shippingRates;
    });

    /* Declare the internal functions of this function */
    function resetUpdateList() {
        setUpdateList({main: false, custom: false});
    }

    /* Detect if shipping rates was changed */
    useEffect(() => {
        if ( shippingRates.length ) {
            setActiveRates(getActiveShippingRates(shippingRates));
        }
    }, [
        shippingRates
    ]);

    /* Get selected shipping rate and customer shipping address */
    useEffect(() => {
        if ( activeRates.length ) {
            for ( let i = 0; i < activeRates.length; i++ ) {
                if ( activeRates[i].selected ) {
                    setCurrentData({...currentData,
                        rate: {
                            id: activeRates[i].rate_id,
                            method: activeRates[i].method_id,
                            instance: activeRates[i].instance_id
                        },
                        destination: getDestination(shippingRates)
                    });
                    setUpdateList({...updateList, main: true});
                    break;
                }
            }
        }
    }, [
        activeRates
    ]);

    /* Get Pakettikauppa method (service) information assigned to the selected shipping rate in the plugin settings */
    useEffect(() => {
        let showBlock = false;
        let currentMethod = getCurrentMethod(currentData.rate.instance);
        if ( JSON.stringify(currentMethod) === JSON.stringify(currentData.method) ) {
            return;
        }
        if ( currentData.rate.id !== '' && currentMethod !== null && currentData.rate.instance ) {
            if ( currentMethod.have_pickups ) {
                showBlock = true;
            }
        }
        setCurrentData({...currentData,
            method: currentMethod,
            show_block: showBlock
        });
        setUpdateList({...updateList, main: true});
    }, [
        currentData.rate
    ]);

    /* Get pickup points list by customer shipping address */
    useEffect(() => {
        if ( ! updateList.main || currentData.method === null ) {
            return;
        }
        if ( currentData.destination === null || ! currentData.method?.service ) {
            console.warn('[Pakettikauppa]', 'Failed to update pickup points:', 'Method data or destination is empty');
            return;
        }
        setCustomAddress('');
        getPickupPoints(currentData.method.service, currentData.destination).then(response => {
            let pickup_points_list = [];
            if ( ! response.success ) {
                console.warn('[Pakettikauppa]', 'Failed to get pickup points:', response.data);
            } else if ( response.data ) {
                pickup_points_list = response.data;
            } else {
                console.warn('[Pakettikauppa]', 'Failed to get pickup points:', 'Data parameter not received');
            }
            if ( JSON.stringify(pickup_points_list) === JSON.stringify(currentData.pickup_points_list) ) {
                return;
            }
            setCurrentData({...currentData,
                pickup_points_list: pickup_points_list,
                pickup_points_list_type: getPluginStaticData().list_type,
                pickup_point: '',
                custom_address: '',
                show_custom: getPluginStaticData().allow_custom_address
            });
        });
        resetUpdateList();
    }, [
        updateList
    ]);

    /* Get pickup points list by value of custom pickup address field */
    useEffect(() => {
        if ( ! updateList.custom ) {
            return;
        }
        if ( currentData.method === null ) {
            console.warn('[Pakettikauppa]', 'Failed to update pickup points by custom address:', 'Method data is empty');
            return;
        }
        getCustomPickupPoints(currentData.method.service, customAddress).then(response => {
            let pickup_points_list = [];
            if ( ! response.success ) {
                console.warn('[Pakettikauppa]', 'Failed to get pickup points:', response.data);
            } else if ( response.data ) {
                pickup_points_list = response.data;
            } else {
                console.warn('[Pakettikauppa]', 'Failed to get pickup points:', 'Data parameter not received');
            }
            setCurrentData({...currentData,
                pickup_points_list: pickup_points_list,
                pickup_point: '',
                custom_address: customAddress
            });
        });
        resetUpdateList();
    }, [
        updateList
    ]);

    /* Execute pickup points list update when custom pickup address field value is no more changing */
    useEffect(() => {
        if ( currentData.method === null ) {
            return;
        }
        if ( ! customAddress.length ) {
            setUpdateList({...updateList, main: true});
            return;
        }
        if ( customAddress.length < 3 || ! isValidAddress(customAddress) ) {
            return;
        }
        setUpdateList({...updateList, custom: true});
    }, [
        debouncedAddress
    ]);

    /* Display an error message if an invalid value is entered in the custom pickup address field */
    useEffect(() => {
        if ( customAddress.length > 0 && customAddress.length < 3 ) {
            setCustomAddressError(txt.custom_pickup_error_too_short);
        } else if ( ! isValidAddress(customAddress) ) {
            setCustomAddressError(txt.custom_pickup_error_bad_char);
        } else {
            setCustomAddressError('');
        }
    }, [
        customAddress
    ]);

    /* Build pickup point select field options */
    useEffect(() => {
        let newPickupOptions = [];
        let label_text = '';
        if (currentData.pickup_points_list_type === 'menu') {
            label_text = '- ' + txt.pickup_select_field_default + ' -';
        }
        if ( ! isMethodPickupRequired(currentData.rate.instance) ) {
            label_text = '- ' + txt.pickup_select_field_optional + ' -';
        }
        if ( label_text !== '' ) {
            newPickupOptions.push({
                label: label_text,
                value: ''
            });
        }
        if ( currentData.pickup_points_list?.length ) {
            for ( let i = 0; i < currentData.pickup_points_list.length; i++ ) {
                newPickupOptions.push({
                    label: currentData.pickup_points_list[i].name + ' (' + currentData.pickup_points_list[i].street_address + ')',
                    value: currentData.pickup_points_list[i].provider + ': ' + currentData.pickup_points_list[i].name + ' (#' + currentData.pickup_points_list[i].pickup_point_id + ')'
                });
            }
        }
        if ( currentData.show_custom ) {
            newPickupOptions.push({
                label: txt.pickup_select_other,
                value: 'other'
            });
        }
        setPickupOptions(newPickupOptions);
    }, [
        currentData.pickup_points_list
    ]);

    /* Save selected pickup point and show error message if not selected */
    useEffect(() => {
        if ( validationError ) {
            clearValidationError(validationErrorId);
            setContainerErrorClass('');
        }

        if ( ! currentData.rate?.instance || ! isMethodHavePickups(currentData.rate.instance) || ! isMethodPickupRequired(currentData.rate.instance) ) {
            return;
        }

        setExtensionData(
            'wc-pakettikauppa',
            'pakettikauppa_pickup_point',
            currentData.pickup_point
        );

        if ( currentData.pickup_point === '' ) {
            setValidationErrors({
                [validationErrorId]: {
                    message: txt.pickup_error,
                    hidden: false
                }
            });
            setContainerErrorClass('error');
        }
    }, [
        setExtensionData,
        currentData.rate?.id,
        currentData.pickup_point
    ]);

    /* Debug data */
    useEffect(() => {
        if (false) {
            console.log('[Debug Pakettikauppa]', currentData);
        }
    }, [
        currentData
    ]);

    /* Render this block */
    if ( ! currentData.show_block ) {
        return <></>
    }

    return (
        <div className={`pakettikauppa-block pakettikauppa-shipping-pickup-point ${containerErrorClass}`}>
            {(! currentData.pickup_points_list?.length) ? (
                <BaseControl label={txt.pickup_block_title}>
                    <p>{txt.pickup_not_found}</p>
                </BaseControl>
            ) : (
                <>
                    {(currentData.pickup_points_list_type === 'list') ? (
                        <RadioControl
                            label={txt.pickup_block_title}
                            help={txt.checkout_pickup_info}
                            selected={currentData.pickup_point}
                            options={pickupOptions}
                            onChange={(value) => setCurrentData({...currentData, pickup_point: value})}
                        />
                    ) : (
                        <SelectControl
                            id="pakettikauppa_pickup_point"
                            label={txt.pickup_block_title}
                            help={txt.checkout_pickup_info}
                            value={currentData.pickup_point}
                            options={pickupOptions}
                            onChange={(value) => setCurrentData({...currentData, pickup_point: value})}
                        />
                    )}
                    {(validationError?.hidden || currentData.pickup_point !== '') ? null : (
                        <div className="wc-block-components-validation-error">
                            <span>{validationError?.message}</span>
                        </div>
                    )}
                </>
            )}

            {(! currentData.custom_address) ? null : (
                <p className={`pakettikauppa-custom-text`}>{txt.custom_pickup_address.replaceAll('%s', '"' + currentData.custom_address + '"')}</p>
            )}

            {(! currentData.show_custom || (currentData.pickup_point !== 'other' && currentData.pickup_points_list?.length)) ? null : (
                <>
                    <TextareaControl
                        label={txt.custom_pickup_title}
                        help={txt.custom_pickup_description + '. ' + txt.custom_pickup_help + '.'}
                        value={customAddress}
                        onChange={(value) => setCustomAddress(value)}
                    />
                    {(customAddressError === '') ? null : (
                        <div className="wc-block-components-validation-error">
                            <span>{customAddressError}</span>
                        </div>
                    )}
                </>
            )}
        </div>
    );
};
