import {orderClientPublic} from "../api/order.client.ts";
import {IdParam} from "../types.ts";
import {useMutation} from "@tanstack/react-query";

export const useTransitionOrderToOfflinePaymentPublic = () => {
    return useMutation({
        mutationFn: ({eventId, orderShortId, proof, paymentReference}: {
            eventId: IdParam,
            orderShortId: IdParam,
            proof?: File | null,
            paymentReference?: string,
        }) => {
            return orderClientPublic.transitionToOfflinePayment(eventId, orderShortId, proof, paymentReference);
        }
    });
}
