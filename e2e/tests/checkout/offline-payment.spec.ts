import { readFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import { test, expect } from '../../fixtures';
import { CheckoutPage } from '../../pages/checkout.page';
import {
  createLiveEventWithPaidTicket,
  enableOfflinePayments,
  OFFLINE_PAYMENT_INSTRUCTIONS,
} from '../../api/factory';
import { uniqueEmail } from '../../utils/unique';

test.describe('offline payment checkout', () => {
  test('a buyer completes an offline-payment order and it is marked as paid', { tag: '@smoke' }, async ({ page, api, account, mailpit }) => {
    const event = await createLiveEventWithPaidTicket(api, account.organizerId);
    await enableOfflinePayments(api, event.eventId);
    const buyerEmail = uniqueEmail('offlinebuyer');
    const buyer = { firstName: 'Offline', lastName: 'Buyer', email: buyerEmail };

    const checkout = new CheckoutPage(page);
    await checkout.gotoPublicEvent(event.eventId, event.slug);
    await checkout.setFirstProductQuantity(1);
    await checkout.continueToCheckout();
    await checkout.fillOrderDetails(buyer);
    await checkout.fillFirstAttendee(buyer);
    await checkout.continueToPayment();
    await checkout.chooseOfflinePayment();

    await expect(page.getByText('Your order is awaiting payment')).toBeVisible();
    await expect(page.getByRole('heading', { name: 'Payment Instructions' })).toBeVisible();
    await expect(page.getByText(OFFLINE_PAYMENT_INSTRUCTIONS)).toBeVisible();

    const emailsBeforePayment = (await mailpit.search(buyerEmail)).length;
    const orderShortId = page.url().match(/\/checkout\/\d+\/([^/?]+)\/summary/)![1];
    const orderId = await api.findOrderIdByShortId(event.eventId, orderShortId);
    await api.markOrderAsPaid(event.eventId, orderId);

    await page.reload();
    await page.waitForLoadState('networkidle');

    await expect(page.getByText(`You're going to ${event.title}`)).toBeVisible();
    await expect(page.getByText('Confirmation sent to')).toBeVisible();
    await expect(page.getByText('Your order is awaiting payment')).toBeHidden();

    await expect
      .poll(async () => (await mailpit.search(buyerEmail)).length, { timeout: 15_000 })
      .toBeGreaterThan(emailsBeforePayment);
    await mailpit.waitForMessage(buyerEmail, { subjectContains: 'Your Order is Confirmed' });
  });

  test('a buyer must attach a valid receipt before paying offline when proof is required', async ({ page, api, account }) => {
    const event = await createLiveEventWithPaidTicket(api, account.organizerId);
    await enableOfflinePayments(api, event.eventId);
    await api.updateEventSettings(event.eventId, { allow_offline_payment_proof: true });
    const buyer = { firstName: 'Receipt', lastName: 'Buyer', email: uniqueEmail('receiptbuyer') };

    const checkout = new CheckoutPage(page);
    await checkout.gotoPublicEvent(event.eventId, event.slug);
    await checkout.setFirstProductQuantity(1);
    await checkout.continueToCheckout();
    await checkout.fillOrderDetails(buyer);
    await checkout.fillFirstAttendee(buyer);
    await checkout.continueToPayment();

    const receiptInput = page.getByLabel('Payment receipt');
    const payButton = page.getByTestId('offline-payment-button');
    await expect(payButton).toBeDisabled();

    await receiptInput.setInputFiles({
      name: 'receipt.txt',
      mimeType: 'text/plain',
      buffer: Buffer.from('not a payment receipt'),
    });
    await expect(page.getByText('Payment receipt must be a JPG, PNG, or PDF')).toBeVisible();
    await expect(payButton).toBeDisabled();

    await receiptInput.setInputFiles({
      name: 'bank-transfer-receipt.png',
      mimeType: 'image/png',
      buffer: readFileSync(fileURLToPath(new URL('../../fixtures/assets/event-cover.png', import.meta.url))),
    });
    await expect(payButton).toBeEnabled();

    await receiptInput.setInputFiles([]);
    await expect(payButton).toBeDisabled();

    await receiptInput.setInputFiles({
      name: 'bank-transfer-receipt.png',
      mimeType: 'image/png',
      buffer: readFileSync(fileURLToPath(new URL('../../fixtures/assets/event-cover.png', import.meta.url))),
    });
    await checkout.chooseOfflinePayment();

    await expect(page.getByText('Payment proof submitted')).toBeVisible();
    await expect(page.getByText('We received your payment proof. You can close this page; the organizer will contact you if anything else is needed.')).toBeVisible();
    await expect(page.getByRole('heading', {name: 'Payment Instructions'})).toBeHidden();
    await expect(page.getByText('bank-transfer-receipt.png')).toBeHidden();
  });
});
