const us_flag = "/images/flags/us_flag.jpg";
const spain_flag = "/images/flags/spain_flag.jpg";
const french_flag = "/images/flags/french_flag.jpg";
const uae_flag = "/images/flags/uae_flag.jpg";
const germany_flag = "/images/flags/germany_flag.jpg";
const china_flag = "/images/flags/china_flag.jpg";
const italy_flag = "/images/flags/italy_flag.jpg";
const russia_flag = "/images/flags/russia_flag.jpg";
const e_13 = "/images/ecommerce/png/13.png";
const e_15 = "/images/ecommerce/png/15.png";
const e_19 = "/images/ecommerce/png/19.png";
const e_11 = "/images/ecommerce/png/11.png";
const e_6 = "/images/ecommerce/png/6.png";
const face1 = "/images/faces/1.jpg";
const face12 = "/images/faces/12.jpg";

interface LanguageType {
    name: string;
    flag: string;
}

interface NotificationNoteType {
    id: number;
    image: string;
    name: string;
    description: string;
    oldprice: string;
    newprice: string;
    off: string;
    liclass?: string;
    quantity: number
}

interface NotificationItem {
    id: number;
    title: string;
    description: string;
    time: string;
    icon?: string;
    avatar?: string;
    isUnread?: boolean;
    liClass?: string;
}

export let
    Languages: LanguageType[] = [
        { name: 'English', flag: us_flag },
        { name: 'Spanish', flag: spain_flag },
        { name: 'français', flag: french_flag },
        { name: 'عربي', flag: uae_flag },
        { name: 'Deutsch', flag: germany_flag },
        { name: '中国人', flag: china_flag },
        { name: 'Italiano', flag: italy_flag },
        { name: 'Русский', flag: russia_flag },
    ],
    notificationNotes: NotificationNoteType[] = [
        { id: 1, quantity: 1, image: e_13, name: "Urban Chic Satchel", description: "Sleek, stylish, and perfect for daily use", oldprice: '$120', newprice: '$90', off: '25%' },
        { id: 2, quantity: 1, image: e_15, name: "TrailBlaze Runners", description: "Lightweight and built for comfort", oldprice: '$80', newprice: '$60', off: '25%' },
        { id: 3, quantity: 1, image: e_19, name: "VisionTech SLR", description: "High-quality shots with every click", oldprice: '$500', newprice: '$375', off: '25%' },
        { id: 4, quantity: 5, image: e_6, name: "FlexiSeat Office Chair", description: "Comfortable support for long hours", oldprice: '$200', newprice: '$150', off: '25%' },
        { id: 5, quantity: 2, image: e_11, name: "DecoDial Classic", description: "A bold, colorful timepiece for any room", oldprice: '$50', newprice: '$35', off: '30%', liclass: "border-bottom-0" },
    ],

    Notifications: NotificationItem[] = [
        {
            id: 1,
            title: "New Message",
            description: "You have received a new message from John Doe",
            time: "11:45am",
            avatar: face1,
            isUnread: false
        },
        {
            id: 2,
            title: "Task Reminder",
            description: "Don't forget to submit your report by 3 PM today",
            time: "02:16pm",
            icon: "ri-notification-line",
            isUnread: false
        },
        {
            id: 3,
            title: "Friend Request",
            description: "Jane Smith sent you a friend request",
            time: "10:04am",
            avatar: face12,
            isUnread: true
        },
        {
            id: 4,
            title: "Event Reminder",
            description: "You have an upcoming event: Team Meeting on October 25 at 10 AM.",
            time: "12:58pm",
            icon: "ri-notification-line",
            isUnread: true
        },
        {
            id: 5,
            title: "File Uploaded",
            description: 'The file "Project_Proposal.pdf" has been uploaded successfully',
            time: "05:13pm",
            icon: "ri-notification-line",
            isUnread: true,
            liClass: 'border-bottom-0'
        }
    ];
