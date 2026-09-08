import {Alert, Button, FileInput, Stack, Text, TextInput} from "@mantine/core";
import {IconCheck, IconFileUpload, IconInfoCircle} from "@tabler/icons-react";
import {useMutation, useQuery, useQueryClient} from "@tanstack/react-query";
import {t} from "@lingui/macro";
import {useState} from "react";
import {orderClientPublic} from "../../../../api/order.client.ts";
import {Event, IdParam, OrderPaymentProof} from "../../../../types.ts";
import {Card} from "../../../common/Card";
import {showError, showSuccess} from "../../../../utilites/notifications.tsx";

const PAYMENT_PROOFS_QUERY_KEY = 'orderPaymentProofs';

const statusLabel = (proof: OrderPaymentProof) => {
    if (proof.status === 'PENDING') return t`Awaiting review`;
    if (proof.status === 'APPROVED') return t`Approved`;
    return t`Rejected`;
};

export const OfflinePaymentProofUpload = ({event, orderShortId}: { event: Event; orderShortId: IdParam }) => {
    const [file, setFile] = useState<File | null>(null);
    const [paymentReference, setPaymentReference] = useState('');
    const queryClient = useQueryClient();
    const eventId = event.id;
    const proofsQuery = useQuery({
        queryKey: [PAYMENT_PROOFS_QUERY_KEY, eventId, orderShortId],
        queryFn: async () => {
            const response = await orderClientPublic.getPaymentProofs(eventId, orderShortId);
            return response.data;
        },
    });
    const proofs = proofsQuery.data ?? [];
    const hasPendingProof = proofs.some((proof) => proof.status === 'PENDING');
    const submitMutation = useMutation({
        mutationFn: () => orderClientPublic.submitPaymentProof(eventId, orderShortId, file!, paymentReference),
        onSuccess: () => {
            setFile(null);
            setPaymentReference('');
            queryClient.invalidateQueries({queryKey: [PAYMENT_PROOFS_QUERY_KEY, eventId, orderShortId]});
            showSuccess(t`Payment proof submitted for review`);
        },
        onError: () => showError(t`Unable to submit payment proof`),
    });

    return (
        <div style={{marginTop: '20px', marginBottom: '40px'}}>
            <h2>{t`Submit Payment Proof`}</h2>
            <Card>
                <Stack gap="md">
                    <Text size="sm">{t`Upload your bank-transfer receipt or payment confirmation. The organizer will review it before marking your order as paid.`}</Text>
                    {proofs.map((proof) => (
                        <Alert
                            key={proof.id}
                            icon={proof.status === 'APPROVED' ? <IconCheck size={16}/> : <IconInfoCircle size={16}/>}
                            color={proof.status === 'REJECTED' ? 'red' : proof.status === 'APPROVED' ? 'green' : 'blue'}
                        >
                            <Text fw={600}>{statusLabel(proof)}</Text>
                            <Text size="sm">{proof.original_filename}</Text>
                            {proof.payment_reference && <Text size="sm">{t`Reference`}: {proof.payment_reference}</Text>}
                            {proof.rejection_reason && <Text size="sm">{t`Reason`}: {proof.rejection_reason}</Text>}
                        </Alert>
                    ))}
                    {!hasPendingProof && (
                        <>
                            <FileInput
                                label={t`Payment receipt`}
                                description={t`JPG, PNG, or PDF up to 10 MB`}
                                placeholder={t`Choose a file`}
                                accept="image/jpeg,image/png,application/pdf"
                                value={file}
                                onChange={setFile}
                                clearable
                            />
                            <TextInput
                                label={t`Payment reference`}
                                description={t`Optional bank transfer or transaction reference`}
                                value={paymentReference}
                                onChange={(event) => setPaymentReference(event.currentTarget.value)}
                            />
                            <Button
                                leftSection={<IconFileUpload size={16}/>} 
                                disabled={!file}
                                loading={submitMutation.isPending}
                                onClick={() => submitMutation.mutate()}
                            >
                                {t`Submit proof`}
                            </Button>
                        </>
                    )}
                </Stack>
            </Card>
        </div>
    );
};
