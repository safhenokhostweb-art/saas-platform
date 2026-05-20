/**
 * Alpine.js Component for Modern Chat-Style Support Ticket System
 * Provides auto-scroll, smooth animations, and interactive chat UI
 */
export default function supportChat() {
    return {
        // State
        message: '',
        isSending: false,
        autoScroll: true,
        chatContainer: null,
        showScrollBtn: false,
        
        // Settings (configurable via data attributes)
        customerBgColor: '#e3f2fd',
        customerTextColor: '#1a1a2e',
        staffBgColor: '#dcf8c6',
        staffTextColor: '#1a1a2e',
        
        init() {
            // Read settings from data attributes
            this.customerBgColor = this.$el.dataset.customerBg || '#e3f2fd';
            this.customerTextColor = this.$el.dataset.customerText || '#1a1a2e';
            this.staffBgColor = this.$el.dataset.staffBg || '#dcf8c6';
            this.staffTextColor = this.$el.dataset.staffText || '#1a1a2e';
            
            this.chatContainer = this.$refs.chatMessages;
            if (this.chatContainer) {
                this.scrollToBottom();
                this.chatContainer.addEventListener('scroll', () => {
                    const { scrollTop, scrollHeight, clientHeight } = this.chatContainer;
                    this.autoScroll = (scrollHeight - scrollTop - clientHeight) < 100;
                    this.showScrollBtn = !this.autoScroll;
                });
            }
        },
        
        scrollToBottom() {
            this.$nextTick(() => {
                if (this.chatContainer) {
                    this.chatContainer.scrollTop = this.chatContainer.scrollHeight;
                }
            });
        },
        
        handleScroll() {
            const el = this.chatContainer;
            if (!el) return;
            const { scrollTop, scrollHeight, clientHeight } = el;
            this.autoScroll = (scrollHeight - scrollTop - clientHeight) < 100;
            this.showScrollBtn = !this.autoScroll;
        },
        
        scrollDown() {
            this.scrollToBottom();
            this.showScrollBtn = false;
            this.autoScroll = true;
        },
        
        async submitReply(event) {
            if (this.isSending || !this.message.trim()) return;
            
            this.isSending = true;
            
            // Let the form submit naturally (standard Laravel form)
            // We just show sending state
            setTimeout(() => {
                this.isSending = false;
            }, 3000);
            
            return true;
        },
        
        formatTime(dateStr) {
            if (!dateStr) return '';
            try {
                const date = new Date(dateStr);
                const hours = date.getHours().toString().padStart(2, '0');
                const minutes = date.getMinutes().toString().padStart(2, '0');
                return `${hours}:${minutes}`;
            } catch (e) {
                return dateStr;
            }
        },
        
        formatDate(dateStr) {
            if (!dateStr) return '';
            try {
                const date = new Date(dateStr);
                const options = { year: 'numeric', month: 'long', day: 'numeric' };
                return date.toLocaleDateString('fa-IR', options);
            } catch (e) {
                return dateStr;
            }
        },
        
        isToday(dateStr) {
            if (!dateStr) return false;
            try {
                const date = new Date(dateStr);
                const today = new Date();
                return date.toDateString() === today.toDateString();
            } catch (e) {
                return false;
            }
        },
        
        isYesterday(dateStr) {
            if (!dateStr) return false;
            try {
                const date = new Date(dateStr);
                const yesterday = new Date();
                yesterday.setDate(yesterday.getDate() - 1);
                return date.toDateString() === yesterday.toDateString();
            } catch (e) {
                return false;
            }
        },
        
        getMessageDateLabel(dateStr) {
            if (this.isToday(dateStr)) return 'امروز';
            if (this.isYesterday(dateStr)) return 'دیروز';
            return this.formatDate(dateStr);
        },
        
        shouldShowDateSeparator(index, replies) {
            if (index === 0) return true;
            const current = new Date(replies[index].created_at);
            const prev = new Date(replies[index - 1].created_at);
            return current.toDateString() !== prev.toDateString();
        },
        
        getMessageStyle(authorType) {
            const isCustomer = authorType === 'customer';
            return {
                backgroundColor: isCustomer ? this.customerBgColor : this.staffBgColor,
                color: isCustomer ? this.customerTextColor : this.staffTextColor,
            };
        },
        
        getMessageAlign(authorType) {
            return authorType === 'customer' ? 'self-start' : 'self-end';
        },
        
        getAvatarInitials(name) {
            if (!name) return '?';
            const parts = name.split(' ');
            if (parts.length >= 2) {
                return (parts[0][0] + parts[1][0]).toUpperCase();
            }
            return name.substring(0, 2).toUpperCase();
        },
        
        getAvatarColor(name) {
            if (!name) return '#6366f1';
            const colors = [
                '#6366f1', '#8b5cf6', '#a855f7', '#d946ef',
                '#ec4899', '#f43f5e', '#ef4444', '#f97316',
                '#eab308', '#84cc16', '#22c55e', '#14b8a6',
                '#06b6d4', '#0ea5e9', '#3b82f6', '#2563eb',
            ];
            let hash = 0;
            for (let i = 0; i < name.length; i++) {
                hash = name.charCodeAt(i) + ((hash << 5) - hash);
            }
            return colors[Math.abs(hash) % colors.length];
        },
    };
}
