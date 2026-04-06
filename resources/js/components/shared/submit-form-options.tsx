import { toast } from 'sonner';

interface FormHandlerProps {
    isEdit: boolean;
    resourceName: string; // e.g., 'Sale' or 'Sublimation'
    onSuccess?: () => void;
    reset?: () => void;
}

export const submitFormOptions = ({
    isEdit,
    resourceName,
    onSuccess,
    reset,
}: FormHandlerProps) => {
    return {
        onSuccess: () => {
            toast.success(
                `${resourceName} ${isEdit ? 'updated' : 'created'} successfully`,
            );

            if (onSuccess) {
                onSuccess();
            }

            if (!isEdit && reset) {
                reset();
            }
        },
        onError: (errors: any) => {
            // Specifically handles your "Only pending transactions" style errors
            if (errors.message) {
                toast.error(errors.message, {
                    closeButton: true,
                });
            } else {
                toast.error('Please check the form for errors.');
            }
        },
        preserveScroll: true,
    };
};
