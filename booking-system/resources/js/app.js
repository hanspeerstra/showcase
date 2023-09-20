import { createApp } from "vue";
import Bookings from "./admin/bookings/views/Bookings.vue";

const bookings = createApp(
    Bookings
);

bookings.mount('#bookings');
