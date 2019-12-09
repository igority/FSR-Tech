<?php

namespace FSR\Http\Controllers\Admin;

use FSR\HubListing;
use FSR\Product;
use FSR\FoodType;
use FSR\ListingOffer;
use FSR\Organization;
use FSR\Custom\Methods;

use FSR\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class HubListingsController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth:master_admin,admin');
    }

    /**
     * Show the application dashboard.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $date_from = substr(Carbon::now()->addDays(-90), 0, 10);
        $date_to_date = Carbon::now();
        $date_to = substr($date_to_date, 0, 10);

        $organizations = Organization::where('status', 'active')
                                      ->where('type', 'hub')->get();

        $organization_filter = '';
        $food_type_filter = '';
        $product_filter = '';

        $food_types = FoodType::where('status', 'active')->get();
        $products = Product::where('status', 'active')->get();
        $hub_listings = HubListing::where('date_expires', '>=', Carbon::now()->format('Y-m-d H:i'))
                                  ->where('date_listed', '>=', $date_from)
                                  ->where('date_listed', '<=', $date_to_date)
                                  ->where('status', 'active')
                                  ->withCount('listing_offers')
                                  ->withCount(['listing_offers' => function ($query) {
                                      $query->where('offer_status', 'active');
                                  }])
                                  ->orderBy('date_expires', 'ASC');


        $selected_filter = 'active';
        return view('admin.hub_listings')->with([
          'hub_listings' => $hub_listings,
          'date_from' => $date_from,
          'date_to' => $date_to,
          'selected_filter' => $selected_filter,
          'organization_filter' => $organization_filter,
          'food_type_filter' => $food_type_filter,
          'product_filter' => $product_filter,
          'organizations' => $organizations,
          'food_types' => $food_types,
          'products' => $products,
        ]);
    }

    /**
     * Handle post request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function handle_post(Request $request)
    {
        $data = $request->all();
        if (!empty($data['post-type'])) {
            switch ($data['post-type']) {
            case 'delete':
              return $this->handle_delete($data);
            case 'filter':
              return $this->handle_filter($data);
            default:
              return $this->index();
            break;
          }
        }
    }

    /**
     * Handle offer listing "delete". (it is actually update)
     *
     * @param  Array $data
     * @return \Illuminate\Http\Response
     */
    public function handle_delete(array $data)
    {
        $hub_listing = $this->delete($data);
        //  $listing->notify(new AdminToVolunteerRemoved($volunteer->organization));
        return back()->with('status', "Донацијата е успешно избришана!");
    }

    /**
     * Handle offer listing "filter".
     *
     * @param  Array $data
     * @return \Illuminate\Http\Response
     */
    public function handle_filter(array $data)
    {
        $date_from = $data["filter_date_from"];
        $date_to = $data["filter_date_to"];
        $date_to_date = Carbon::parse($date_to)->addDays(1);
        $organization_filter = $data["organizations-filter-select"];
        $food_type_filter = $data["food-types-filter-select"];
        $product_filter = $data["products-filter-select"];
        $organizations = Organization::where('status', 'active')
                                      ->where('type', 'hub')->get();

        $food_types = FoodType::where('status', 'active')->get();
        $products = Product::where('status', 'active')->get();
        $selected_filter = $data["donations-filter-select"];
        switch ($selected_filter) {
          case 'active':
            $listing_status_operator = ">";
            break;
          case 'past':
            $listing_status_operator = "<";
            break;

          default:
            $listing_status_operator = ">";
            break;
        }
        $hub_listings = HubListing::where('date_expires', $listing_status_operator, Carbon::now()->format('Y-m-d H:i'))
                                ->where('date_listed', '>=', $date_from)
                                ->where('date_listed', '<=', $date_to_date)
                                ->where('status', 'active')
                                ->withCount('listing_offers')
                                ->withCount(['listing_offers' => function ($query) {
                                    $query->where('offer_status', 'active');
                                }])
                                ->orderBy('date_expires', 'ASC');


        if ($organization_filter != '') {
            $hub_listings->whereHas('hub', function ($query) use ($organization_filter) {
                $query->where('organization_id', $organization_filter);
            });
        }

        if ($food_type_filter != '') {
            $hub_listings->whereHas('product', function ($query) use ($food_type_filter) {
                $query->where('food_type_id', $food_type_filter);
            });
        }

        if ($product_filter != '') {
            $hub_listings->where('product_id', $product_filter);
        }

        return view('admin.hub_listings')->with([
        'hub_listings' => $hub_listings,
        'date_from' => $date_from,
        'date_to' => $date_to,
        'selected_filter' => $selected_filter,
        'organization_filter' => $organization_filter,
        'food_type_filter' => $food_type_filter,
        'product_filter' => $product_filter,
        'organizations' => $organizations,
        'food_types' => $food_types,
        'products' => $products,

      ]);
    }

    /**
     * Mark the selected listing offer as cancelled
     *
     * @param  array  $data
     * @return \FSR\Volunteer
     */
    protected function delete(array $data)
    {
        $hub_listing = HubListing::find($data['hub_listing_id']);
        $hub_listing->status = 'deleted';
        $hub_listing->save();
        return $hub_listing;
    }
}
