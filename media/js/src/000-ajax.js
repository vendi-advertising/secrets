/*jslint esversion: 6, maxparams: 5, maxdepth: 5, maxstatements: 20, maxcomplexity: 8 */

const ajax = {};
ajax.x = function()
        {
            'use strict';                         //Force strict mode
            if ( typeof XMLHttpRequest !== 'undefined' ){
                return new XMLHttpRequest();
            }

            throw 'XMLHttpRequest not supported in browser';
    };

ajax._make_query_from_data = (data) => {
            'use strict';                         //Force strict mode
            const
                query = []
            ;
            let
                key,
                sub_key
            ;
            for ( key in data ){
                if( data.hasOwnProperty( key ) ){
                    if(Array.isArray(data[key])){
                        for( sub_key in data[key]){
                            if(data[key].hasOwnProperty(sub_key)){
                                query.push( encodeURIComponent( key ) + '[]=' + encodeURIComponent( data[ key ][ sub_key ] ) );
                            }
                        }
                    }else{
                        query.push( encodeURIComponent( key ) + '=' + encodeURIComponent( data[ key ] ) );
                    }
                }
            }

            return query;
        };

ajax.send = ( url, callback, method, data ) => {
                'use strict';                         //Force strict mode
                const
                    x = ajax.x()
                ;
                x.open( method, url, true );

                if(callback instanceof Function){
                    x.onreadystatechange =  () =>  {
                                                if ( 4 === x.readyState )
                                                {
                                                    callback( x.responseText, x );
                                                }
                                            };
                }

                if ( 'POST' === method ){
                    x.setRequestHeader('Content-type', 'application/x-www-form-urlencoded');
                }

                x.send(data);
            };

ajax.get = ( url, data, callback ) => {
                'use strict';                         //Force strict mode
                const
                    query = ajax._make_query_from_data(data)
                ;

                if( null === data ){
                    ajax.send( url, callback, 'GET', null );
                } else {
                    ajax.send( url + '?' + query.join( '&' ), callback, 'GET', null );
                }
            };

ajax.post = ( url, data, callback, sync ) => {
                'use strict';                         //Force strict mode
                const
                    query = ajax._make_query_from_data(data)
                ;

                ajax.send( url, callback, 'POST', query.join( '&' ) );
            };
