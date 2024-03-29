<?php

namespace Huoban\Models;

use Huoban\Models\HuobanBasic;
use Huoban\Models\Package\Item;
use Huoban\Models\Package\Items;

class HuobanItem extends HuobanBasic
{
    use Item;
    use Items;

    public function getCount( $table, $body = [], $options = [] )
    {
        $body['table_id'] = $table;
        return $this->request->execute( 'POST', "/item/count", $body, $options );
    }

    public function getFiltered( $table, $body = [], $options = [] )
    {
        $response = $this->getCount( $table, $body, $options );

        return $response['filtered'];
    }

    public function getTotal( $table, $body = [], $options = [] )
    {
        $response = $this->getCount( $table, $body, $options );

        return $response['total'];
    }

    public function findRequest( $table, $body = [], $options = [] )
    {
        $body['limit'] = $body['limit'] ?? 500;

        return $this->request->getRequest( 'POST', "/item/table/{$table}/find", $body, $options );
    }

    public function find( $table, $body = [], $options = [] )
    {
        $body['limit'] = $body['limit'] ?? 500;
        return $this->request->execute( 'POST', "/item/table/{$table}/find", $body, $options );
    }

    public function findAllRequest( $table, $body = [], $options = [] )
    {
        $requests = [];
        $filtered = $this->getFiltered( $table, $body, $options );

        // 查询全部数据的所有请求
        $body['limit'] = 500;
        for ( $i = 0; $i < ceil( $filtered / $body['limit'] ); $i++ ) {

            $body['offset'] = $body['limit'] * $i;
            $requests[]     = $this->findRequest( $table, $body, $options );
        }
        return $requests;

    }
    public function findAll( $table, $body = [], $options = [] )
    {
        $responses = [];
        $requests  = $this->findAllRequest( $table, $body, $options );
        // 返回结果集,key为item_id
        $responses = $this->request->requestJsonPool( $requests );

        $return_data = [];
        foreach ( $responses['success_data'] as $success_response ) {
            if ( ! $return_data ) {
                $return_data = $success_response['response'];
            }
            else {
                $return_data['items'] = $return_data['items'] + array_combine( array_column( $success_response['response']['items'], 'item_id' ), $success_response['response']['items'] );
            }

        }

        return $return_data;
    }

    public function statsRequest( $table_id, $body = [], $options = [] )
    {
        $body['limit'] = $body['limit'] ?? 500;
        return $this->request->getRequest( 'POST', "/item/table/{$table_id}/stats", $body, $options );
    }
    public function stats( $table_id, $body = [], $options = [] )
    {

        $body['limit'] = $body['limit'] ?? 500;
        return $this->request->execute( 'POST', "/item/table/{$table_id}/stats", $body, $options );
    }

    public function updateRequest( $item_id, $body = [], $options = [] )
    {
        return $this->request->getRequest( 'PUT', "/item/{$item_id}", $body, $options );
    }
    public function update( $item_id, $body = [], $options = [] )
    {
        return $this->request->execute( 'PUT', "/item/{$item_id}", $body, $options );
    }

    public function updatesRequest( $table, $body = [], $options = [] )
    {
        return $this->request->getRequest( 'POST', "/item/table/{$table}/update", $body, $options );
    }
    public function updates( $table, $body = [], $options = [] )
    {
        return $this->request->execute( 'POST', "/item/table/{$table}/update", $body, $options );
    }

    public function createRequest( $table, $body = null, $options = [] )
    {
        return $this->request->getRequest( 'POST', "/item/table/{$table}", $body, $options );
    }
    public function create( $table, $body = null, $options = [] )
    {
        return $this->request->execute( 'POST', "/item/table/{$table}", $body, $options );
    }

    public function createsRequest( $table, $body = null, $options = [] )
    {
        return $this->request->getRequest( 'POST', "/item/table/{$table}/create", $body, $options );
    }
    public function creates( $table, $body = null, $options = [] )
    {
        return $this->request->execute( 'POST', "/item/table/{$table}/create", $body, $options );
    }

    public function delRequest( $item_id, $body = null, $options = [] )
    {
        return $this->request->getRequest( 'POST', "/item/{$item_id}", $body, $options );
    }
    public function del( $item_id, $body = null, $options = [] )
    {
        return $this->request->execute( 'DELETE', "/item/{$item_id}", $body, $options );
    }

    public function delsRequest( $table, $body = null, $options = [] )
    {
        return $this->request->getRequest( 'DELETE', "/item/table/{$table}/delete", $body, $options );
    }
    public function dels( $table, $body = null, $options = [] )
    {
        return $this->request->execute( 'POST', "/item/table/{$table}/delete", $body, $options );
    }

    public function getRequest( $item_id, $body = null, $options = [] )
    {
        return $this->request->getRequest( 'GET', "/item/{$item_id}", $body, $options );
    }
    public function get( $item_id, $body = null, $options = [] )
    {
        return $this->request->execute( 'GET', "/item/{$item_id}", $body, $options );
    }

    public function handleItems( $items )
    {
        $format_items = [];
        foreach ( $items as $item ) {
            $item_id                  = (string) $item['item_id'];
            $format_items[ $item_id ] = $this->returnDiy( $item );
        }
        return $format_items;
    }

    public function returnDiy( $item, $type = 'api' )
    {
        $format_item = [];
        foreach ( $item['fields'] as $field ) {
            if ( $field ) {

                $field_key = $field['alias'] ?: (string) $field['field_id'];
                $field_key = 'api' == $type ? $field_key : str_replace( '.', '-', $field_key );

                switch ($field['type']) {
                    case 'number':
                    case 'text':
                    case 'calculation':
                    case 'date':
                        $format_item[ $field_key ] = $field['values'][0]['value'];
                        break;
                    case 'user':
                        $format_item[ $field_key ] = $field['values'][0]['name'];
                        $format_item[ $field_key . '_uid' ] = $field['values'][0]['user_id'];
                        break;
                    case 'relation':
                        $ids = [];
                        $titles = [];
                        foreach ( $field['values'] as $value ) {
                            $ids[]    = $value['item_id'];
                            $titles[] = $value['title'];
                        }
                        $format_item[ $field_key ] = implode( ',', $titles );
                        $format_item[ $field_key . '_ids' ] = $ids;
                        $format_item[ $field_key . '_titles' ] = $titles;
                        break;
                    case 'category':
                        $ids = [];
                        $names = [];
                        foreach ( $field['values'] as $value ) {
                            $ids[]   = $value['id'];
                            $names[] = $value['name'];
                        }
                        $format_item[ $field_key ] = implode( ',', $names );
                        $format_item[ $field_key . '_ids' ] = $ids;
                        $format_item[ $field_key . '_names' ] = $names;
                        break;
                    case 'image':
                        $sources = [];
                        foreach ( $field['values'] as $value ) {
                            $sources[] = $value['link']['source'];
                        }
                        $format_item[ $field_key ] = implode( ';', $sources );
                        $format_item[ $field_key . '_linksource' ] = $sources;

                        $names = [];
                        $fileids = [];
                        foreach ( $field['values'] as $value ) {
                            $names[]   = $value['name'];
                            $fileids[] = $value['file_id'];
                        }
                        $format_item[ $field_key . '_file_ids' ] = $fileids;
                        $format_item[ $field_key . '_names' ] = $names;
                        break;
                    case 'signature':
                        $user = $field['values'][0]['user'];
                        $file = $field['values'][0]['file'];
                        $format_item[ $field_key ] = $file['link']['source'];
                        $format_item[ $field_key . '_user' ] = $user;
                        break;
                    default:
                        break;
                }
            }
        }
        $format_item['item_id'] = $item['item_id'];
        return $format_item;
    }
}
