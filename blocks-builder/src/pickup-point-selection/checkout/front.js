/**
 * System external functions
 **/
import { useEffect, useState, useCallback } from '@wordpress/element';
import { useDispatch, useSelect } from '@wordpress/data';
import { SelectControl, RadioControl, TextareaControl, Flex, FlexItem, BaseControl } from '@wordpress/components';

/**
 * This plugin external functions and variables
 **/
import { txt } from '../global/text';
import { getActiveShippingRates, getDestination } from '../global/wc';
import { pluginParams, getPluginStaticData, getCurrentMethod, isMethodHavePickups, isMethodPickupRequired, getPickupPoints, getCustomPickupPoints } from '../global/plugin';
import { useDebounce, isValidAddress, compareObjects } from '../global/utils';

/**
 * Exporting functions of this file
 **/
export const Block = ({ checkoutExtensionData, extension }) => {
    /* Declare this function variables */
    const { setExtensionData } = checkoutExtensionData;
    const [ activeRates, setActiveRates ] = useState([]);
    const [ currentData, setCurrentData ] = useState({
        data_loaded: false,
        rate: null,
        destination: null,
        method: null,
        update_list: 'main',
        pickup_points: {
            list: [],
            type: "",
            selected: ""
        },
        custom_address: "",
        show_block: false,
        show_custom: false,
    });
    const [ pickupOptions, setPickupOptions ] = useState([]);
    const validationErrorId = pluginParams.pickup_point_error_id;
    const [ customAddress, setCustomAddress ] = useState('');
    const debouncedCustomAddress = useDebounce(customAddress, 2000);
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
    const { shippingRates, shippingAddress } = useSelect((select) => {
        const store = select('wc/store/cart');
        return {
            shippingRates: store.getCartData().shippingRates,
            shippingAddress: store.getCartData().shippingAddress,
        };
    });
    const debouncedShippingAddress = useDebounce(shippingAddress, 1500);

    /* Declare the internal functions of this function */
    function showWarning( ...msgs ) {
        console.log('[' + pluginParams.name + ' warning]', ...msgs);
    }

    /* Check if all required data has been loaded */
    const isRequiredDataLoaded = useCallback(() => {
        return currentData.method && currentData.destination;
    }, [
        currentData.method, currentData.destination
    ]);

    useEffect(() => {
        if (isRequiredDataLoaded()) {
            setCurrentData({...currentData,
                data_loaded: true
            });
        }
    }, [
        isRequiredDataLoaded
    ]);

    /* Detect if shipping rates was changed */
    useEffect(() => {
        if ( shippingRates.length ) {
            setActiveRates(getActiveShippingRates(shippingRates));
        }
    }, [
        shippingRates
    ]);

    /* Detect if shipping address was changed */
    useEffect(() => {
        if ( ! shippingAddress.country ) {
            return;
        }
        let temp_dest = {
            country: shippingAddress.country,
            address: shippingAddress?.address_1 || '',
            city: shippingAddress?.city || '',
            postcode: shippingAddress?.postcode || '',
        };
        if ( ! compareObjects(currentData.destination, temp_dest) ) {
            setCurrentData({...currentData,
                destination: temp_dest,
                data_loaded: false
            });
        }
    }, [
        debouncedShippingAddress
    ]);

    /* Get selected rate ID */
    useEffect(() => {
        if ( ! activeRates.length ) {
            return;
        }
        for ( let i = 0; i < activeRates.length; i++ ) {
            if ( activeRates[i].selected && (! currentData.rate || currentData.rate.id != activeRates[i].rate_id) ) {
                setCurrentData({...currentData,
                    rate: {
                        id: activeRates[i].rate_id,
                        method: activeRates[i].method_id,
                        instance: activeRates[i].instance_id
                    },
                    data_loaded: false
                });
            }
        }
    }, [
        activeRates
    ]);

    /* Get Pakettikauppa method (service) information assigned to the selected shipping rate in the plugin settings */
    useEffect(() => {
        if ( ! currentData.rate ) {
            return;
        }

        let showBlock = false;
        let currentMethod = getCurrentMethod(currentData.rate.instance);
        if ( compareObjects(currentMethod, currentData.method) ) {
            return;
        }
        if ( currentData.rate.id !== '' && currentMethod !== null && currentData.rate.instance ) {
            if ( currentMethod.have_pickups ) {
                showBlock = true;
            }
        }
        setCurrentData({...currentData,
            method: currentMethod,
            show_block: showBlock,
            update_list: 'main'
        });
    }, [
        currentData.rate
    ]);

    /* Get pickup points list by customer shipping address */
    useEffect(() => {
        if ( ! currentData.data_loaded || currentData.update_list != 'main' ) {
            return;
        }
        if ( ! currentData.method?.service ) {
            showWarning('Failed to update pickup points:', 'Method data is empty');
            return;
        }
        //setCustomAddress('');
        getPickupPoints(currentData.method.service, currentData.destination).then(response => {
            let pickup_points_list = [];
            if ( ! response.success ) {
                showWarning('Failed to get pickup points:', response.data);
            } else if ( response.data ) {
                pickup_points_list = response.data;
            } else {
                showWarning('Failed to get pickup points:', 'Data parameter not received');
            }
            if ( compareObjects(pickup_points_list, currentData.pickup_points.list) ) {
                return;
            }
            setCurrentData({...currentData,
                pickup_points: {
                    ...currentData.pickup_points,
                    list: pickup_points_list,
                    type: getPluginStaticData().list_type,
                },
                show_custom: getPluginStaticData().allow_custom_address
            });
        });
    }, [
        currentData.update_list,
        currentData.data_loaded
    ]);

    /* Get pickup points list by value of custom pickup address field */
    useEffect(() => {
        if ( ! currentData.data_loaded || currentData.update_list != 'custom' || ! currentData.custom_address ) {
            return;
        }
        if ( ! currentData.method?.service ) {
            showWarning('Failed to update pickup points by custom address:', 'Method data is empty');
            return;
        }
        getCustomPickupPoints(currentData.method.service, currentData.custom_address).then(response => {
            let pickup_points_list = [];
            if ( ! response.success ) {
                showWarning('Failed to get pickup points by custom address:', response.data);
            } else if ( response.data ) {
                pickup_points_list = response.data;
            } else {
                showWarning('Failed to get pickup points by custom address:', 'Data parameter not received');
            }
            setCurrentData({...currentData,
                pickup_points: {
                    ...currentData.pickup_points,
                    list: pickup_points_list,
                }
            });
        });
    }, [
        currentData.data_loaded,
        currentData.update_list
    ]);

    /* Execute pickup points list update when custom pickup address field value is no more changing */
    useEffect(() => {
        if ( ! currentData.method ) {
            return;
        }
        if ( ! customAddress.length ) {
            setCurrentData({...currentData,
                update_list: 'main',
                custom_address: ''
            });
            return;
        }
        if ( customAddress.length < 3 || ! isValidAddress(customAddress) ) {
            return;
        }
        setCurrentData({...currentData,
            update_list: 'custom',
            custom_address: customAddress,
        });
    }, [
        debouncedCustomAddress
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
        if ( ! currentData.data_loaded ) {
            return;
        }

        let newPickupOptions = [];
        let label_text = '';
        if (currentData.pickup_points.type === 'menu') {
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
        if ( currentData.pickup_points.list?.length ) {
            for ( let i = 0; i < currentData.pickup_points.list.length; i++ ) {
                let pickup_point = currentData.pickup_points.list[i];
                newPickupOptions.push({
                    label: pickup_point.name + ' (' + pickup_point.street_address + ')',
                    value: pickup_point.provider + ': ' + pickup_point.name + ' (#' + pickup_point.pickup_point_id + ')'
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
        currentData.pickup_points?.list
    ]);

    /* Save selected pickup point and show error message if not selected */
    useEffect(() => {
        if ( validationError ) {
            clearValidationError(validationErrorId);
            setContainerErrorClass('');
        }

        if ( ! currentData.rate?.instance || ! isMethodHavePickups(currentData.rate.instance) ) {
            return;
        }

        setExtensionData(
            'wc-pakettikauppa',
            'pakettikauppa_pickup_point',
            currentData.pickup_points.selected
        );

        if ( isMethodPickupRequired(currentData.rate.instance) && currentData.pickup_points.selected === '' ) {
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
        currentData.pickup_points?.selected
    ]);

    /* Debug data */
    useEffect(() => {
        if (false) { //Change to true to enable debug
            console.log('[' + pluginParams.name + ' debug]', currentData);
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
            {(! currentData.pickup_points.list?.length) ? (
                <BaseControl label={txt.pickup_block_title}>
                    <p>{txt.pickup_not_found}</p>
                </BaseControl>
            ) : (
                <>
                    {(currentData.pickup_points.type === 'list') ? (
                        <RadioControl
                            label={txt.pickup_block_title}
                            help={txt.checkout_pickup_info}
                            selected={currentData.pickup_points.selected}
                            options={pickupOptions}
                            onChange={(value) => setCurrentData({...currentData,
                                pickup_points: {
                                    ...currentData.pickup_points,
                                    selected: value
                                }
                            })}
                        />
                    ) : (
                        <SelectControl
                            id="pakettikauppa_pickup_point"
                            label={txt.pickup_block_title}
                            help={txt.checkout_pickup_info}
                            value={currentData.pickup_points.selected}
                            options={pickupOptions}
                            onChange={(value) => setCurrentData({...currentData,
                                pickup_points: {
                                    ...currentData.pickup_points,
                                    selected: value
                                }
                            })}
                        />
                    )}
                </>
            )}
            {(validationError?.hidden || currentData.pickup_points.selected !== '') ? null : (
                <div className="wc-block-components-validation-error">
                    <span>{validationError?.message}</span>
                </div>
            )}

            {(! currentData.custom_address) ? null : (
                <p className={`pakettikauppa-custom-text`}>{txt.custom_pickup_address.replaceAll('%s', '"' + currentData.custom_address + '"')}</p>
            )}

            {(! currentData.show_custom || (currentData.pickup_points.selected !== 'other' && currentData.pickup_points.list?.length)) ? null : (
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
