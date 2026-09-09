import {Alert, Button, Group, Loader, Stack, Text, Textarea} from '@mantine/core';
import {IconCheck, IconDownload, IconEye, IconX} from '@tabler/icons-react';
import {useMutation, useQuery, useQueryClient} from '@tanstack/react-query';
import {t} from '@lingui/macro';
import {useEffect, useState} from 'react';
import {orderClient} from '../../../api/order.client.ts';
import {IdParam, OrderPaymentProof} from '../../../types.ts';
import {showError, showSuccess} from '../../../utilites/notifications.tsx';
import {Modal} from '../../common/Modal';

const PAYMENT_PROOFS_QUERY_KEY = 'orderPaymentProofs';

export const OrderPaymentProofReview = ({eventId, orderId, onReviewed}: {eventId: IdParam; orderId: IdParam; onReviewed: () => void}) => {
    const [rejectionReason, setRejectionReason] = useState('');
    const [previewedProof, setPreviewedProof] = useState<OrderPaymentProof | null>(null);
    const [previewUrl, setPreviewUrl] = useState<string | null>(null);
    const [previewError, setPreviewError] = useState(false);
    const queryClient = useQueryClient();
    const proofsQuery = useQuery({
        queryKey: [PAYMENT_PROOFS_QUERY_KEY, eventId, orderId],
        queryFn: async () => {
            const response = await orderClient.getPaymentProofs(eventId, orderId);
            return response.data;
        }
    });
    const invalidate = () =>
        queryClient.invalidateQueries({
            queryKey: [PAYMENT_PROOFS_QUERY_KEY, eventId, orderId]
        });
    const approveMutation = useMutation({
        mutationFn: (paymentProofId: IdParam) => orderClient.approvePaymentProof(eventId, orderId, paymentProofId),
        onSuccess: () => {
            invalidate();
            onReviewed();
            showSuccess(t`Payment proof approved`);
        },
        onError: () => showError(t`Unable to approve payment proof`)
    });
    const rejectMutation = useMutation({
        mutationFn: (paymentProofId: IdParam) => orderClient.rejectPaymentProof(eventId, orderId, paymentProofId, rejectionReason),
        onSuccess: () => {
            setRejectionReason('');
            invalidate();
            showSuccess(t`Payment proof rejected`);
        },
        onError: () => showError(t`Unable to reject payment proof`)
    });
    const handleDownload = async (proof: OrderPaymentProof) => {
        try {
            const blob = await orderClient.downloadPaymentProof(eventId, orderId, proof.id);
            const url = URL.createObjectURL(blob);
            const link = document.createElement('a');
            link.href = url;
            link.download = proof.original_filename;
            link.click();
            URL.revokeObjectURL(url);
        } catch {
            showError(t`Unable to download payment proof`);
        }
    };

    const handlePreview = (proof: OrderPaymentProof) => {
        setPreviewUrl(null);
        setPreviewError(false);
        setPreviewedProof(proof);
    };

    const closePreview = () => setPreviewedProof(null);

    useEffect(() => {
        if (!previewedProof) return;

        let active = true;
        let objectUrl: string | null = null;

        orderClient
            .downloadPaymentProof(eventId, orderId, previewedProof.id)
            .then(blob => {
                objectUrl = URL.createObjectURL(new Blob([blob], {type: previewedProof.mime_type}));

                if (active) {
                    setPreviewUrl(objectUrl);
                } else if (objectUrl) {
                    URL.revokeObjectURL(objectUrl);
                }
            })
            .catch(() => {
                if (active) setPreviewError(true);
            });

        return () => {
            active = false;
            if (objectUrl) URL.revokeObjectURL(objectUrl);
        };
    }, [eventId, orderId, previewedProof]);

    if (proofsQuery.isLoading || !proofsQuery.data?.length) {
        return null;
    }

    return (
        <Stack gap="md">
            {proofsQuery.data.map(proof => (
                <Stack key={proof.id} gap="xs">
                    <Group justify="space-between" align="center">
                        <div>
                            <Text fw={600}>{proof.original_filename}</Text>
                            <Text size="sm" c="dimmed">
                                {proof.status}
                            </Text>
                            {proof.payment_reference && (
                                <Text size="sm">
                                    {t`Reference`}: {proof.payment_reference}
                                </Text>
                            )}
                            {proof.rejection_reason && (
                                <Text size="sm" c="red">
                                    {t`Reason`}: {proof.rejection_reason}
                                </Text>
                            )}
                            {proof.reviewed_at && (
                                <Text size="sm" c="dimmed">
                                    {t`Reviewed`}: {new Date(proof.reviewed_at).toLocaleString()}
                                </Text>
                            )}
                        </div>
                        <Group gap="xs">
                            <Button variant="light" leftSection={<IconEye size={16} />} onClick={() => handlePreview(proof)} data-testid="payment-proof-preview-button">
                                {t`Preview`}
                            </Button>
                            <Button variant="light" leftSection={<IconDownload size={16} />} onClick={() => handleDownload(proof)}>
                                {t`Download`}
                            </Button>
                        </Group>
                    </Group>
                    {proof.status === 'PENDING' && (
                        <>
                            <Textarea label={t`Rejection reason`} value={rejectionReason} onChange={event => setRejectionReason(event.currentTarget.value)} minRows={2} />
                            <Group>
                                <Button color="green" leftSection={<IconCheck size={16} />} loading={approveMutation.isPending} onClick={() => approveMutation.mutate(proof.id)} data-testid="payment-proof-approve-button">
                                    {t`Approve and mark paid`}
                                </Button>
                                <Button color="red" variant="light" leftSection={<IconX size={16} />} disabled={!rejectionReason.trim()} loading={rejectMutation.isPending} onClick={() => rejectMutation.mutate(proof.id)} data-testid="payment-proof-reject-button">
                                    {t`Reject proof`}
                                </Button>
                            </Group>
                        </>
                    )}
                </Stack>
            ))}
            <Modal opened={previewedProof !== null} onClose={closePreview} heading={t`Payment proof preview`} size="xl">
                {previewedProof && (
                    <Stack gap="md">
                        <Group justify="space-between">
                            <Text fw={600}>{previewedProof.original_filename}</Text>
                            <Button variant="light" leftSection={<IconDownload size={16} />} onClick={() => handleDownload(previewedProof)}>
                                {t`Download`}
                            </Button>
                        </Group>
                        {previewError && <Alert color="red">{t`Preview unavailable. Download the receipt instead.`}</Alert>}
                        {!previewError && !previewUrl && (
                            <Group justify="center" gap="sm" py="xl">
                                <Loader size="sm" />
                                <Text>{t`Loading preview`}</Text>
                            </Group>
                        )}
                        {previewUrl &&
                            (previewedProof.mime_type === 'application/pdf' ? (
                                <iframe title={previewedProof.original_filename} src={previewUrl} style={{border: 0, height: '70vh', width: '100%'}} />
                            ) : (
                                <img
                                    src={previewUrl}
                                    alt={previewedProof.original_filename}
                                    style={{
                                        height: 'auto',
                                        maxHeight: '70vh',
                                        maxWidth: '100%',
                                        objectFit: 'contain',
                                        width: '100%'
                                    }}
                                />
                            ))}
                    </Stack>
                )}
            </Modal>
        </Stack>
    );
};
