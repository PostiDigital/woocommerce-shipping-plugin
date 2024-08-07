import { useEffect, useState } from '@wordpress/element';

export const buildToken = ( length ) => {
    let token = '';
    const characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
    const charactersLength = characters.length;
    let counter = 0;
    while (counter < length) {
        token += characters.charAt(Math.floor(Math.random() * charactersLength));
        counter += 1;
    }
    return token;
};

export const addTokenToValue = ( value, tokenLength = 5 ) => {
    return {
        value: value,
        token: buildToken(tokenLength)
    };
};

export const useDebounce = ( cb, delay ) => {
  const [debounceValue, setDebounceValue] = useState(cb);
  useEffect(() => {
    const handler = setTimeout(() => {
      setDebounceValue(cb);
    }, delay);

    return () => {
      clearTimeout(handler);
    };
  }, [cb, delay]);
  return debounceValue;
};

export const isValidAddress = ( value ) => {
    let regex = /[`!@#$%^*_+=\[\]{};:|<>\/?~]/;
    return ! regex.test(value);
};
