<?php

namespace App\Http\Controllers\Web\Admin;

use App\Base\Constants\Masters\zoneRideType;
use App\Base\Filters\Master\CommonMasterFilter;
use App\Base\Libraries\QueryFilter\QueryFilterContract;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Zone\AssignZoneTypeRequest;
use App\Models\Admin\VehicleType;
use App\Models\Admin\Zone;
use App\Models\Admin\ZoneTypePrice;
use Illuminate\Http\Request;
use App\Models\Admin\ZoneType;
use App\Base\Constants\Auth\Role;

class VehicleFareController extends Controller
{

    public function zoneIndex(Zone $zone)
    {
        $page = trans('pages_names.owners');
        $main_menu = 'vehicle-fare';
        $sub_menu = $zone->name;

        return view('admin.vehicle_fare.zoneIndex', compact('page', 'main_menu', 'sub_menu', 'zone'));
    }
    public function getAllPrice(QueryFilterContract $queryFilter,Zone $zone)
    {
        
            $results = ZoneTypePrice::whereHas('zoneType', function ($query) use ($zone) {
            $query->where('zone_id', $zone->id);
            })
            ->orderBy('created_at', 'desc') // Add this line to order by created_at in descending order
            ->paginate();

        return view('admin.vehicle_fare._set_price', compact('results'))->render();
    }



    public function fetchFareList(QueryFilterContract $queryFilter)
    {
        $query = ZoneTypePrice::latest();

        $results = $queryFilter->builder($query)->customFilter(new CommonMasterFilter)->paginate();

        return view('admin.vehicle_fare._fare_list', compact('results'));
    }

    public function create() 
    {
        $zones = Zone::active()->get();
        $page = trans('pages_names.add_vehicle_fare');
        $main_menu = 'vehicle-fare';
        $sub_menu = '';

        return view('admin.vehicle_fare.create', compact('page', 'main_menu', 'sub_menu', 'zones'));
    }

    public function fetchVehiclesByZone(Request $request)
    {
        $zone = Zone::whereId($request->_zone)->first();
        $ids = $zone->zoneType()->pluck('type_id')->toArray();

        $types = VehicleType::whereNotIn('id', $ids)->active()->get();

        return response()->json(['success' => true, 'data' => $types]);
    }

   public function store(AssignZoneTypeRequest $request)
    {
        $zone  = Zone::whereId($request->zone)->first();

        if (auth()->user()->hasRole(!(Role::ADMIN))) {
        
        // dd($request);
        if (env('APP_FOR')=='demo') {
            $message = trans('succes_messages.you_are_not_authorised');

        return redirect('vehicle_fare/by_zone/'.$zone->id)->with('success', $message);
        }
    }

        $payment = implode(',', $request->payment_type);

        // To save default type
        if ($zone->default_vehicle_type == null) {
            $zone->default_vehicle_type = $request->type;
            $zone->save();
        }

        $zoneType = $zone->zoneType()->create([
            'type_id' => $request->type,
            'payment_type' => $payment,
            'admin_commision_type' => $request->admin_commision_type,
            'admin_commision' => $request->admin_commision,
            'service_tax' => $request->service_tax,
            'bill_status' => true
        ]);

        $zoneType->zoneTypePrice()->create([
            'price_type' => zoneRideType::RIDENOW,
            'base_price' => $request->ride_now_base_price,
            'price_per_distance' => $request->ride_now_price_per_distance,
            'free_waiting_time_in_mins_before_trip_start' => $request->ride_now_free_waiting_time_in_mins_before_trip_start,
            'free_waiting_time_in_mins_after_trip_start' => $request->ride_now_free_waiting_time_in_mins_after_trip_start,
            'waiting_charge' => $request->ride_now_waiting_charge,
            'cancellation_fee' => $request->ride_now_cancellation_fee,
            'base_distance' => $request->ride_now_base_distance ? $request->ride_now_base_distance : 0,
            'price_per_time' => $request->ride_now_price_per_time ? $request->ride_now_price_per_time : 0.00,
        ]);

        $zoneType->zoneTypePrice()->create([
            'price_type' => zoneRideType::RIDELATER,
            'base_price' => $request->ride_later_base_price,
            'price_per_distance' => $request->ride_later_price_per_distance,
            'cancellation_fee' => $request->ride_later_cancellation_fee,
            'base_distance' => $request->ride_later_base_distance ? $request->ride_later_base_distance : 0,
            'price_per_time' => $request->ride_later_price_per_time ? $request->ride_later_price_per_time : 0.00,
            'waiting_charge' => $request->ride_later_waiting_charge,
            'free_waiting_time_in_mins_before_trip_start' => $request->ride_later_free_waiting_time_in_mins_before_trip_start,
            'free_waiting_time_in_mins_after_trip_start' => $request->ride_later_free_waiting_time_in_mins_after_trip_start,
        ]);

        $message = trans('succes_messages.type_assigned_succesfully');

        return redirect('vehicle_fare/by_zone/'.$zone->id)->with('success', $message);
    }

    public function getById(ZoneTypePrice $zone_price)
    {
        $page = trans('pages_names.edit_vehicle_fare');
        $main_menu = 'vehicle-fare';
        $sub_menu = '';

        return view('admin.vehicle_fare.edit', compact('page', 'main_menu', 'sub_menu', 'zone_price'));
    }

    public function update(Request $request,ZoneTypePrice $zone_price)
    {
        $zone_id = $zone_price->zoneType()->pluck('zone_id')->toArray();


    if (auth()->user()->hasRole(!(Role::ADMIN))) {
      if (env('APP_FOR')=='demo') {
            $message = trans('succes_messages.you_are_not_authorised');

        return redirect('vehicle_fare/by_zone/'.$zone_id[0])->with('success', $message);
        }
     }   
        $zone_price->zoneType()->update([
            'payment_type' => implode(',', $request->payment_type),
            'admin_commision_type' => $request->admin_commision_type,
            'admin_commision' => $request->admin_commision,
            'service_tax' => $request->service_tax,
        ]);
        if($zone_price->price_type == 1)
        {
        $zone_price->update([
            'base_price' => $request->ride_now_base_price,
            'price_per_distance' => $request->ride_now_price_per_distance,
            'cancellation_fee' => $request->ride_now_cancellation_fee,
            'base_distance' => $request->ride_now_base_distance ? $request->ride_now_base_distance : 0,
            'price_per_time' => $request->ride_now_price_per_time ? $request->ride_now_price_per_time : 0.00,
            'waiting_charge' => $request->ride_now_waiting_charge,
            'free_waiting_time_in_mins_before_trip_start' => $request->ride_now_free_waiting_time_in_mins_before_trip_start,
            'free_waiting_time_in_mins_after_trip_start' => $request->ride_now_free_waiting_time_in_mins_after_trip_start,
        ]);
        }else{
        $zone_price->update([
            'base_price' => $request->ride_later_base_price,
            'price_per_distance' => $request->ride_later_price_per_distance,
            'cancellation_fee' => $request->ride_later_cancellation_fee,
            'base_distance' => $request->ride_later_base_distance ? $request->ride_later_base_distance : 0,
            'price_per_time' => $request->ride_later_price_per_time ? $request->ride_later_price_per_time : 0.00,
            'waiting_charge' => $request->ride_later_waiting_charge,
            'free_waiting_time_in_mins_before_trip_start' => $request->ride_later_free_waiting_time_in_mins_before_trip_start,
            'free_waiting_time_in_mins_after_trip_start' => $request->ride_later_free_waiting_time_in_mins_after_trip_start,
        ]);
        }
        $message = trans('succes_messages.type_fare_updated_succesfully');

        return redirect('vehicle_fare/by_zone/'.$zone_id[0])->with('success', $message);
    }

    public function toggleStatus(ZoneTypePrice $zone_price) {

        $status = $zone_price->zoneType->isActive() ? false : true;
        $zone_price->zoneType->update(['active' => $status]);

        $message = trans('succes_messages.type_fare_status_updated_succesfully');

        return redirect('vehicle_fare')->with('success', $message);
    }
    public function delete(ZoneTypePrice $zone_price)
    {
        if (auth()->user()->hasRole(!(Role::ADMIN))) {
          if (env('APP_FOR')=='demo') {
                $message = trans('succes_messages.you_are_not_authorised');

                return redirect('vehicle_fare')->with('warning', $message);
            }
         }  

        $zone_type = ZoneType::where('id', $zone_price->zone_type_id)->get();
        // $package = ZoneTypePackagePrice::where('zone_type_id', $zone_price->zone_type_id)->get();
        
        foreach($zone_type as $type)
        {
          $type->delete();
          $type->zoneTypePrice()->delete();
        //   $package->delete();

        }
        $message = trans('succes_messages.vehicle_fare_deleted_succesfully');

        return $message;
 

    }
}
